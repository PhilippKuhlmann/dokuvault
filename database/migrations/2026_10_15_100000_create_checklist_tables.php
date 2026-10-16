<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * On- and offboarding checklists.
 *
 * - checklist_options: what a customer can tick in the onboarding form
 *   (network drives, programs, hardware), maintained per customer by IT.
 * - checklist_templates / _items: the standard steps. An item without
 *   customer_id belongs to everybody, one with customer_id only to that
 *   customer. checklist_template_item_hidden switches a standard item off
 *   for one customer - so new standard items still reach every customer.
 * - checklists / checklist_items: a started list. The items are copied on
 *   start, so a later change to a template does not alter a running list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // drive, app, hardware
            $table->string('name');
            $table->string('drive_letter', 2)->nullable();
            $table->string('path')->nullable();
            $table->string('ad_group')->nullable();
            $table->string('detail')->nullable();
            $table->string('assignee', 20)->default('it'); // it, customer
            $table->boolean('needs_approval')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->unique(); // onboarding, offboarding
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->string('title');
            $table->string('assignee', 20)->default('it');
            $table->integer('due_offset')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('checklist_template_item_hidden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Own index name: the generated one has 66 characters, MySQL allows 64.
            $table->foreignId('checklist_template_item_id')->constrained(indexName: 'checklist_hidden_item_foreign')->cascadeOnDelete();
            $table->unique(['customer_id', 'checklist_template_item_id'], 'checklist_hidden_unique');
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position')->nullable();
            $table->string('supervisor')->nullable();
            $table->date('reference_date');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('ad_user_id')->nullable()->constrained('ad_users')->nullOnDelete();
            $table->json('request')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->string('title');
            $table->string('reference')->nullable();
            $table->string('hint')->nullable();
            $table->string('assignee', 20)->default('it');
            $table->integer('due_offset')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('needs_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        $this->seedStandardTemplates();
    }

    /**
     * The standard steps every customer starts with. Sort numbers leave room:
     * generated items (drives, programs, hardware) sit between 400 and 700.
     */
    private function seedStandardTemplates(): void
    {
        $now = now();
        $templates = [
            'onboarding' => ['Onboarding', [
                ['Konten', 'AD-Benutzer {username} anlegen', 'it', -5, 100],
                ['Konten', 'Kennwort setzen, Änderung bei der ersten Anmeldung erzwingen', 'it', -5, 110],
                ['Erster Tag', 'Zugangsdaten persönlich übergeben, MFA einrichten', 'it', 0, 900],
                ['Erster Tag', 'Kurze Einweisung: Anmeldung, Drucker, Ablage', 'it', 0, 910],
            ]],
            'offboarding' => ['Offboarding', [
                ['Konto', 'AD-Benutzer {username} deaktivieren', 'it', 0, 100],
                ['Konto', 'Kennwort zurücksetzen, aktive Sitzungen abmelden', 'it', 0, 110],
                ['Rückgabe', 'Hardware, Schlüssel und Transponder zurücknehmen', 'customer', 0, 600],
                ['Abschluss', 'Daten aus dem Home-Laufwerk an Vorgesetzte übergeben', 'it', 7, 800],
                ['Abschluss', 'AD-Benutzer löschen', 'it', 30, 900],
            ]],
        ];

        foreach ($templates as $type => [$name, $items]) {
            $id = DB::table('checklist_templates')->insertGetId([
                'type' => $type, 'name' => $name, 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('checklist_template_items')->insert(array_map(fn ($item) => [
                'checklist_template_id' => $id,
                'section' => $item[0],
                'title' => $item[1],
                'assignee' => $item[2],
                'due_offset' => $item[3],
                'sort' => $item[4],
                'created_at' => $now,
                'updated_at' => $now,
            ], $items));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('checklist_template_item_hidden');
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('checklist_options');
    }
};
