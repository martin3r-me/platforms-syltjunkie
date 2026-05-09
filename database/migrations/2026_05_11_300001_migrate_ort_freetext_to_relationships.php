<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // entity_type_id for "ort" entities
        $ortTypeId = DB::table('sj_entity_types')
            ->where('code', 'ort')
            ->value('id');

        if (!$ortTypeId) {
            return;
        }

        // relation_type_id for "lokalisiert_in"
        $relationTypeId = 1;

        // Get all Ort-Entities keyed by name
        $ortEntities = DB::table('sj_entities')
            ->where('entity_type_id', $ortTypeId)
            ->whereNull('deleted_at')
            ->pluck('id', 'name');

        // Migrate non-Ort entities: create relationships from ort freetext
        $nonOrtEntities = DB::table('sj_entities')
            ->where('entity_type_id', '!=', $ortTypeId)
            ->whereNotNull('ort')
            ->where('ort', '!=', '')
            ->whereNull('deleted_at')
            ->get(['id', 'team_id', 'ort']);

        foreach ($nonOrtEntities as $entity) {
            $ortEntityId = $ortEntities->get($entity->ort);

            if (!$ortEntityId) {
                continue;
            }

            // Check if relationship already exists
            $exists = DB::table('sj_entity_relationships')
                ->where('source_entity_id', $entity->id)
                ->where('target_entity_id', $ortEntityId)
                ->where('relation_type_id', $relationTypeId)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('sj_entity_relationships')->insert([
                    'uuid' => \Symfony\Component\Uid\UuidV7::generate(),
                    'team_id' => $entity->team_id,
                    'source_entity_id' => $entity->id,
                    'target_entity_id' => $ortEntityId,
                    'relation_type_id' => $relationTypeId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Clear ort freetext
            DB::table('sj_entities')
                ->where('id', $entity->id)
                ->update(['ort' => null]);
        }

        // Clear ort on Ort-Entities themselves (redundant self-reference)
        DB::table('sj_entities')
            ->where('entity_type_id', $ortTypeId)
            ->whereNotNull('ort')
            ->update(['ort' => null]);
    }

    public function down(): void
    {
        $ortTypeId = DB::table('sj_entity_types')
            ->where('code', 'ort')
            ->value('id');

        if (!$ortTypeId) {
            return;
        }

        $relationTypeId = 1;

        // Restore ort freetext from relationships
        $relationships = DB::table('sj_entity_relationships')
            ->where('relation_type_id', $relationTypeId)
            ->whereNull('deleted_at')
            ->join('sj_entities as target', 'sj_entity_relationships.target_entity_id', '=', 'target.id')
            ->where('target.entity_type_id', $ortTypeId)
            ->select('sj_entity_relationships.source_entity_id', 'target.name as ort_name')
            ->get();

        foreach ($relationships as $rel) {
            DB::table('sj_entities')
                ->where('id', $rel->source_entity_id)
                ->update(['ort' => $rel->ort_name]);
        }

        // Restore ort on Ort-Entities themselves
        $ortEntities = DB::table('sj_entities')
            ->where('entity_type_id', $ortTypeId)
            ->whereNull('deleted_at')
            ->get(['id', 'name']);

        foreach ($ortEntities as $entity) {
            DB::table('sj_entities')
                ->where('id', $entity->id)
                ->update(['ort' => $entity->name]);
        }
    }
};
