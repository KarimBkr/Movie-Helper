<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists "pgcrypto"');

        // Enum types
        DB::statement("create type public.project_type as enum ('film','serie','court_metrage','pilote','autre')");
        DB::statement("create type public.project_status as enum ('active','deleting','deleted')");
        DB::statement("create type public.project_member_role as enum ('owner','viewer')");
        DB::statement("create type public.script_parse_status as enum ('uploaded','parsing','parsed','parse_failed')");
        DB::statement("create type public.sequence_status as enum ('ia','verifie','corrige','manuel')");
        DB::statement("create type public.sequence_parse_status as enum ('parsed','needs_review','parse_failed')");
        DB::statement("create type public.element_category as enum ('personnage','accessoire','vehicule','arme','animal','sfx_vfx','costume','continuite','flag','note')");
        DB::statement("create type public.element_status as enum ('ia','verifie','corrige','manuel')");
        DB::statement("create type public.confidence_level as enum ('high','medium','low')");
        DB::statement("create type public.analysis_job_status as enum ('pending','running','completed','partial','failed','cancelled')");
        DB::statement("create type public.analysis_item_status as enum ('pending','running','completed','failed','skipped')");

        // Tables
        DB::statement('
            create table public.profiles (
                id uuid primary key references auth.users(id) on delete cascade,
                email text not null,
                display_name text,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        ');

        DB::statement('
            create table public.projects (
                id uuid primary key default gen_random_uuid(),
                title text not null,
                slug text not null,
                type public.project_type not null,
                shooting_day_duration integer not null default 600,
                owner_id uuid not null references auth.users(id) on delete restrict,
                status public.project_status not null default \'active\',
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                deleted_at timestamptz,
                constraint projects_title_not_empty check (char_length(trim(title)) > 0),
                constraint projects_slug_not_empty check (char_length(trim(slug)) > 0),
                constraint projects_shooting_day_duration_positive check (shooting_day_duration > 0)
            )
        ');

        DB::statement('
            create table public.project_members (
                id uuid primary key default gen_random_uuid(),
                project_id uuid not null references public.projects(id) on delete cascade,
                user_id uuid not null references auth.users(id) on delete cascade,
                role public.project_member_role not null,
                created_at timestamptz not null default now(),
                unique (project_id, user_id)
            )
        ');

        DB::statement('
            create table public.scripts (
                id uuid primary key default gen_random_uuid(),
                project_id uuid not null references public.projects(id) on delete cascade,
                uploaded_by uuid not null references auth.users(id) on delete restrict,
                file_name text not null,
                storage_bucket text not null default \'fdx-files\',
                storage_path text not null,
                file_size_bytes bigint,
                parse_status public.script_parse_status not null default \'uploaded\',
                parse_error text,
                sequence_count integer not null default 0,
                is_active boolean not null default true,
                created_at timestamptz not null default now(),
                parsed_at timestamptz,
                constraint scripts_file_name_not_empty check (char_length(trim(file_name)) > 0),
                constraint scripts_storage_path_not_empty check (char_length(trim(storage_path)) > 0),
                constraint scripts_file_size_positive check (file_size_bytes is null or file_size_bytes > 0)
            )
        ');

        DB::statement('
            create table public.sequences (
                id uuid primary key default gen_random_uuid(),
                project_id uuid not null references public.projects(id) on delete cascade,
                script_id uuid not null references public.scripts(id) on delete cascade,
                display_order integer not null,
                scene_number text,
                scene_heading text,
                decor text,
                sub_decor text,
                int_ext text not null default \'UNKNOWN\',
                day_night text not null default \'UNKNOWN\',
                raw_text text not null,
                resume text,
                huitiemes numeric(5,2),
                parse_status public.sequence_parse_status not null default \'parsed\',
                status public.sequence_status not null default \'ia\',
                flags text[] not null default \'{}\',
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                validated_at timestamptz,
                validated_by uuid references auth.users(id) on delete set null,
                constraint sequences_display_order_positive check (display_order > 0),
                constraint sequences_raw_text_not_empty check (char_length(trim(raw_text)) > 0),
                constraint sequences_huitiemes_valid check (huitiemes is null or huitiemes >= 0)
            )
        ');

        DB::statement('
            create table public.sequence_elements (
                id uuid primary key default gen_random_uuid(),
                project_id uuid not null references public.projects(id) on delete cascade,
                sequence_id uuid not null references public.sequences(id) on delete cascade,
                category public.element_category not null,
                value text not null,
                source_text text,
                confidence public.confidence_level not null default \'medium\',
                status public.element_status not null default \'ia\',
                note text,
                created_by uuid references auth.users(id) on delete set null,
                updated_by uuid references auth.users(id) on delete set null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                deleted_at timestamptz,
                constraint sequence_elements_value_not_empty check (char_length(trim(value)) > 0),
                constraint sequence_elements_ai_source_required check (
                    status <> \'ia\'
                    or (source_text is not null and char_length(trim(source_text)) > 0)
                )
            )
        ');

        DB::statement('
            create table public.analysis_jobs (
                id uuid primary key default gen_random_uuid(),
                project_id uuid not null references public.projects(id) on delete cascade,
                script_id uuid not null references public.scripts(id) on delete cascade,
                requested_by uuid not null references auth.users(id) on delete restrict,
                status public.analysis_job_status not null default \'pending\',
                total_sequences integer not null default 0,
                processed_sequences integer not null default 0,
                failed_sequences integer not null default 0,
                error_message text,
                started_at timestamptz,
                finished_at timestamptz,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                constraint analysis_jobs_counts_valid check (
                    total_sequences >= 0
                    and processed_sequences >= 0
                    and failed_sequences >= 0
                    and processed_sequences <= total_sequences
                    and failed_sequences <= total_sequences
                )
            )
        ');

        DB::statement('
            create table public.analysis_job_items (
                id uuid primary key default gen_random_uuid(),
                project_id uuid not null references public.projects(id) on delete cascade,
                analysis_job_id uuid not null references public.analysis_jobs(id) on delete cascade,
                sequence_id uuid not null references public.sequences(id) on delete cascade,
                status public.analysis_item_status not null default \'pending\',
                attempts integer not null default 0,
                error_message text,
                claude_tool_use_id text,
                raw_tool_input jsonb,
                started_at timestamptz,
                finished_at timestamptz,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                unique (analysis_job_id, sequence_id),
                constraint analysis_job_items_attempts_valid check (attempts >= 0 and attempts <= 2)
            )
        ');

        DB::statement('
            create table public.exports (
                id uuid primary key default gen_random_uuid(),
                project_id uuid not null references public.projects(id) on delete cascade,
                requested_by uuid not null references auth.users(id) on delete restrict,
                storage_bucket text,
                storage_path text,
                file_name text not null,
                created_at timestamptz not null default now(),
                constraint exports_file_name_not_empty check (char_length(trim(file_name)) > 0)
            )
        ');

        // Indexes
        DB::statement('create unique index projects_slug_unique_idx on public.projects(slug)');
        DB::statement('create index profiles_email_idx on public.profiles(email)');
        DB::statement('create index projects_owner_id_idx on public.projects(owner_id)');
        DB::statement('create index projects_status_idx on public.projects(status)');
        DB::statement('create index project_members_project_id_idx on public.project_members(project_id)');
        DB::statement('create index project_members_user_id_idx on public.project_members(user_id)');
        DB::statement('create index project_members_project_role_idx on public.project_members(project_id, role)');
        DB::statement('create index scripts_project_id_idx on public.scripts(project_id)');
        DB::statement('create index scripts_project_active_idx on public.scripts(project_id, is_active)');
        DB::statement('create index scripts_parse_status_idx on public.scripts(parse_status)');
        DB::statement('create index sequences_project_id_idx on public.sequences(project_id)');
        DB::statement('create index sequences_script_id_idx on public.sequences(script_id)');
        DB::statement('create index sequences_project_order_idx on public.sequences(project_id, display_order)');
        DB::statement('create index sequences_status_idx on public.sequences(status)');
        DB::statement('create unique index sequences_script_order_unique on public.sequences(script_id, display_order)');
        DB::statement('create index sequence_elements_project_id_idx on public.sequence_elements(project_id)');
        DB::statement('create index sequence_elements_sequence_id_idx on public.sequence_elements(sequence_id)');
        DB::statement('create index sequence_elements_category_idx on public.sequence_elements(category)');
        DB::statement('create index sequence_elements_status_idx on public.sequence_elements(status)');
        DB::statement('create index sequence_elements_confidence_idx on public.sequence_elements(confidence)');
        DB::statement('create index sequence_elements_not_deleted_idx on public.sequence_elements(sequence_id) where deleted_at is null');
        DB::statement('create index analysis_jobs_project_id_idx on public.analysis_jobs(project_id)');
        DB::statement('create index analysis_jobs_status_idx on public.analysis_jobs(status)');
        DB::statement('create index analysis_job_items_job_id_idx on public.analysis_job_items(analysis_job_id)');
        DB::statement('create index analysis_job_items_project_id_idx on public.analysis_job_items(project_id)');
        DB::statement('create index analysis_job_items_status_idx on public.analysis_job_items(status)');
        DB::statement('create index exports_project_id_idx on public.exports(project_id)');

        // updated_at trigger function
        DB::statement('
            create or replace function public.set_updated_at()
            returns trigger language plpgsql as $$
            begin
                new.updated_at = now();
                return new;
            end;
            $$
        ');

        foreach (['profiles', 'projects', 'sequences', 'sequence_elements', 'analysis_jobs', 'analysis_job_items'] as $table) {
            DB::statement("
                create trigger {$table}_set_updated_at
                before update on public.{$table}
                for each row execute function public.set_updated_at()
            ");
        }

        // Auto-create owner membership on project insert
        DB::statement('
            create or replace function public.create_owner_membership()
            returns trigger language plpgsql security definer set search_path = public as $$
            begin
                insert into public.project_members (project_id, user_id, role)
                values (new.id, new.owner_id, \'owner\')
                on conflict (project_id, user_id) do nothing;
                return new;
            end;
            $$
        ');

        DB::statement('
            create trigger projects_create_owner_membership
            after insert on public.projects
            for each row execute function public.create_owner_membership()
        ');

        // RLS helper functions
        DB::statement('
            create or replace function public.is_project_member(p_project_id uuid)
            returns boolean language sql security definer stable set search_path = public as $$
                select exists (
                    select 1 from public.project_members pm
                    where pm.project_id = p_project_id and pm.user_id = auth.uid()
                )
            $$
        ');

        DB::statement('
            create or replace function public.is_project_owner(p_project_id uuid)
            returns boolean language sql security definer stable set search_path = public as $$
                select exists (
                    select 1 from public.project_members pm
                    where pm.project_id = p_project_id and pm.user_id = auth.uid() and pm.role = \'owner\'
                ) or exists (
                    select 1 from public.projects p
                    where p.id = p_project_id and p.owner_id = auth.uid()
                )
            $$
        ');

        DB::statement('
            create or replace function public.can_read_project(p_project_id uuid)
            returns boolean language sql security definer stable set search_path = public as $$
                select public.is_project_member(p_project_id)
            $$
        ');

        DB::statement('
            create or replace function public.can_write_project(p_project_id uuid)
            returns boolean language sql security definer stable set search_path = public as $$
                select public.is_project_owner(p_project_id)
            $$
        ');

        DB::statement('
            create or replace function public.storage_project_id_from_path(object_name text)
            returns uuid language plpgsql stable as $$
            declare
                first_segment text;
            begin
                first_segment := split_part(object_name, \'/\', 1);
                if first_segment ~* \'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$\' then
                    return first_segment::uuid;
                end if;
                return null;
            end;
            $$
        ');

        // RLS — enable
        foreach ([
            'profiles', 'projects', 'project_members', 'scripts',
            'sequences', 'sequence_elements', 'analysis_jobs', 'analysis_job_items', 'exports',
        ] as $table) {
            DB::statement("alter table public.{$table} enable row level security");
        }

        // RLS — policies
        DB::statement('create policy "profiles_select_own" on public.profiles for select to authenticated using (id = auth.uid())');
        DB::statement('create policy "profiles_insert_own" on public.profiles for insert to authenticated with check (id = auth.uid())');
        DB::statement('create policy "profiles_update_own" on public.profiles for update to authenticated using (id = auth.uid()) with check (id = auth.uid())');

        DB::statement('create policy "projects_select_members" on public.projects for select to authenticated using (public.can_read_project(id))');
        DB::statement('create policy "projects_insert_owner_self" on public.projects for insert to authenticated with check (owner_id = auth.uid())');
        DB::statement('create policy "projects_update_owner" on public.projects for update to authenticated using (public.can_write_project(id)) with check (public.can_write_project(id))');
        DB::statement('create policy "projects_delete_owner" on public.projects for delete to authenticated using (public.can_write_project(id))');

        DB::statement('create policy "project_members_select_project_members" on public.project_members for select to authenticated using (public.can_read_project(project_id))');
        DB::statement('create policy "project_members_insert_owner" on public.project_members for insert to authenticated with check (public.can_write_project(project_id))');
        DB::statement('create policy "project_members_update_owner" on public.project_members for update to authenticated using (public.can_write_project(project_id)) with check (public.can_write_project(project_id))');
        DB::statement('create policy "project_members_delete_owner" on public.project_members for delete to authenticated using (public.can_write_project(project_id))');

        foreach (['scripts', 'sequences', 'analysis_jobs', 'analysis_job_items'] as $table) {
            DB::statement("create policy \"{$table}_select_members\" on public.{$table} for select to authenticated using (public.can_read_project(project_id))");
            DB::statement("create policy \"{$table}_insert_owner\" on public.{$table} for insert to authenticated with check (public.can_write_project(project_id))");
            DB::statement("create policy \"{$table}_update_owner\" on public.{$table} for update to authenticated using (public.can_write_project(project_id)) with check (public.can_write_project(project_id))");
            DB::statement("create policy \"{$table}_delete_owner\" on public.{$table} for delete to authenticated using (public.can_write_project(project_id))");
        }

        DB::statement('create policy "sequence_elements_select_members" on public.sequence_elements for select to authenticated using (public.can_read_project(project_id))');
        DB::statement('create policy "sequence_elements_insert_owner" on public.sequence_elements for insert to authenticated with check (public.can_write_project(project_id))');
        DB::statement('create policy "sequence_elements_update_owner" on public.sequence_elements for update to authenticated using (public.can_write_project(project_id)) with check (public.can_write_project(project_id))');
        DB::statement('create policy "sequence_elements_delete_owner" on public.sequence_elements for delete to authenticated using (public.can_write_project(project_id))');

        DB::statement('create policy "exports_select_members" on public.exports for select to authenticated using (public.can_read_project(project_id))');
        DB::statement('create policy "exports_insert_members" on public.exports for insert to authenticated with check (public.can_read_project(project_id))');
        DB::statement('create policy "exports_delete_owner" on public.exports for delete to authenticated using (public.can_write_project(project_id))');

        // Storage buckets (requires Supabase postgres user)
        DB::statement("insert into storage.buckets (id, name, public) values ('fdx-files', 'fdx-files', false) on conflict (id) do nothing");
        DB::statement("insert into storage.buckets (id, name, public) values ('exports', 'exports', false) on conflict (id) do nothing");

        DB::statement('create policy "storage_fdx_select_members" on storage.objects for select to authenticated using (bucket_id = \'fdx-files\' and public.can_read_project(public.storage_project_id_from_path(name)))');
        DB::statement('create policy "storage_fdx_insert_owner" on storage.objects for insert to authenticated with check (bucket_id = \'fdx-files\' and public.can_write_project(public.storage_project_id_from_path(name)))');
        DB::statement('create policy "storage_fdx_update_owner" on storage.objects for update to authenticated using (bucket_id = \'fdx-files\' and public.can_write_project(public.storage_project_id_from_path(name))) with check (bucket_id = \'fdx-files\' and public.can_write_project(public.storage_project_id_from_path(name)))');
        DB::statement('create policy "storage_fdx_delete_owner" on storage.objects for delete to authenticated using (bucket_id = \'fdx-files\' and public.can_write_project(public.storage_project_id_from_path(name)))');
        DB::statement('create policy "storage_exports_select_members" on storage.objects for select to authenticated using (bucket_id = \'exports\' and public.can_read_project(public.storage_project_id_from_path(name)))');
        DB::statement('create policy "storage_exports_insert_members" on storage.objects for insert to authenticated with check (bucket_id = \'exports\' and public.can_read_project(public.storage_project_id_from_path(name)))');
        DB::statement('create policy "storage_exports_delete_owner" on storage.objects for delete to authenticated using (bucket_id = \'exports\' and public.can_write_project(public.storage_project_id_from_path(name)))');
    }

    public function down(): void
    {
        // Storage policies
        foreach (['storage_fdx_select_members','storage_fdx_insert_owner','storage_fdx_update_owner','storage_fdx_delete_owner','storage_exports_select_members','storage_exports_insert_members','storage_exports_delete_owner'] as $policy) {
            DB::statement("drop policy if exists \"{$policy}\" on storage.objects");
        }

        // Application tables (reverse order)
        DB::statement('drop table if exists public.exports cascade');
        DB::statement('drop table if exists public.analysis_job_items cascade');
        DB::statement('drop table if exists public.analysis_jobs cascade');
        DB::statement('drop table if exists public.sequence_elements cascade');
        DB::statement('drop table if exists public.sequences cascade');
        DB::statement('drop table if exists public.scripts cascade');
        DB::statement('drop table if exists public.project_members cascade');
        DB::statement('drop table if exists public.projects cascade');
        DB::statement('drop table if exists public.profiles cascade');

        // Functions
        foreach (['set_updated_at','create_owner_membership','is_project_member','is_project_owner','can_read_project','can_write_project','storage_project_id_from_path'] as $fn) {
            DB::statement("drop function if exists public.{$fn} cascade");
        }

        // Enum types
        foreach (['project_type','project_status','project_member_role','script_parse_status','sequence_status','sequence_parse_status','element_category','element_status','confidence_level','analysis_job_status','analysis_item_status'] as $type) {
            DB::statement("drop type if exists public.{$type} cascade");
        }
    }
};
