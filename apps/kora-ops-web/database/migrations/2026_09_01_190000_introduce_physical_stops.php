<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_stops', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('city');
            $table->string('country', 2)->default('BJ');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            // Un même nom d'arrêt physique peut exister dans plusieurs villes
            // (ex. « Gare Routière » à Cotonou et à Lomé), mais le couple
            // (name, city) identifie de façon unique un point d'arrêt physique.
            // Les seeders s'appuient sur cet invariant via firstOrCreate(['name' => …]).
            $table->unique(['name', 'city']);
        });

        // Étape 1 — ajouter la colonne nullable pour permettre le backfill
        // sur les environnements (staging/prod) où `stops` contient déjà des lignes.
        // Ajouter directement NOT NULL ferait échouer la migration sur tout
        // environnement non greenfield.
        Schema::table('stops', function (Blueprint $table): void {
            $table->foreignId('physical_stop_id')
                ->after('line_id')
                ->nullable()
                ->constrained('physical_stops')
                ->restrictOnDelete();
        });

        // Étape 2 — backfill : créer un PhysicalStop par (name, city) existant
        // et lier chaque stop à son point physique. Idempotent grâce au
        // unique(name, city) : un même point physique partagé par plusieurs
        // lignes n'est créé qu'une fois.
        $now = now();
        foreach (DB::table('stops')->get() as $stop) {
            $physicalId = DB::table('physical_stops')
                ->where('name', $stop->name)
                ->where('city', $stop->city)
                ->value('id');

            if ($physicalId === null) {
                $physicalId = DB::table('physical_stops')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => $stop->name,
                    'city' => $stop->city,
                    'country' => $stop->country,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'status' => $stop->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('stops')
                ->where('id', $stop->id)
                ->update(['physical_stop_id' => $physicalId]);
        }

        // Étape 3 — s'assurer qu'aucun stop n'est resté orphelin, puis
        // passer la colonne en NOT NULL.
        $orphans = DB::table('stops')->whereNull('physical_stop_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "Impossible de passer stops.physical_stop_id en NOT NULL : {$orphans} arrêt(s) sans point physique."
            );
        }

        Schema::table('stops', function (Blueprint $table): void {
            $table->foreignId('physical_stop_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stops', function (Blueprint $table): void {
            $table->dropForeign(['physical_stop_id']);
            $table->dropColumn('physical_stop_id');
        });

        Schema::dropIfExists('physical_stops');
    }
};
