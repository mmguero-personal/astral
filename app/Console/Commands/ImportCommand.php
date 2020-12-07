<?php

/*
  See "import existing stars/tags when self-hosting" (https://github.com/astralapp/astral/issues/232)
  Adapted from https://github.com/astralapp/astral/issues/232#issuecomment-448655090
*/

namespace Astral\Console\Commands;

use Astral\Models\Tag;
use Astral\Models\Star;
use Astral\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportCommand extends Command
{
    protected $signature = 'astral:import {jsonFile=astral_data.json}';

    protected $description = 'Import your data from the json export';

    public function handle()
    {
        Schema::disableForeignKeyConstraints();
        Star::truncate();
        Tag::truncate();
        Schema::enableForeignKeyConstraints();

        $path = base_path($this->argument('jsonFile'));
        $content = collect(json_decode(file_get_contents($path), true));

        $user = User::first();

        $content->each(function ($starWithTags) use ($user) {
            $starData = [
                'user_id' => $user->id,
                'repo_id' => $starWithTags['repo_id'],
                'notes' => $starWithTags['notes'],
                'created_at' => $starWithTags['created_at'],
                'updated_at' => $starWithTags['updated_at'],
                'autotagged_by_topic' => $starWithTags['autotagged_by_topic'],
            ];

            DB::table('stars')->updateOrInsert([
                'repo_id' => $starWithTags['repo_id'],
            ], $starData);

            $star = Star::where('repo_id', $starData['repo_id'])->first();

            $tags = collect($starWithTags['tags']);

            $tags->each(function ($tag) use ($user, $star) {
                $tagData = [
                    'user_id' => $user->id,
                    'name' => $tag['name'],
                    'sort_order' => $tag['sort_order'],
                    'created_at' => $tag['created_at'],
                    'updated_at' => $tag['updated_at'],
                    'id' => $tag['id'],
                ];

                DB::table('tags')->updateOrInsert([
                    'id' => $tagData['id'],
                ], $tagData);

                $tag = Tag::where('id', $tagData['id'])->first();

                $starTagData = [
                    'star_id' => $star->id,
                    'tag_id' => $tag->id,
                ];

                DB::table('star_tag')->updateOrInsert($starTagData, $starTagData);
            });
        });
    }
}
