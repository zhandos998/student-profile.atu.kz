<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyStudentServiceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_requests_are_preserved_and_imported_into_the_new_schema(): void
    {
        $student = User::factory()->create();

        Schema::drop('student_service_requests');
        Schema::create('student_service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('student_profile_id')->nullable();
            $table->foreignId('handled_by_id')->nullable();
            $table->string('type');
            $table->string('status');
            $table->text('comment')->nullable();
            $table->text('response_comment')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        DB::table('student_service_requests')->insert([
            'id' => 17,
            'user_id' => $student->id,
            'type' => 'id_card',
            'status' => 'new',
            'comment' => 'Нужна новая карта',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            '2026_11_03_000001_preserve_legacy_student_service_requests.php',
            '2026_11_04_000001_create_student_service_requests_table.php',
            '2026_11_04_000002_add_id_card_fields_to_student_service_requests.php',
            '2026_11_04_000003_import_legacy_student_service_requests.php',
        ] as $filename) {
            (require database_path('migrations/'.$filename))->up();
        }

        $this->assertTrue(Schema::hasTable('legacy_student_service_requests'));
        $this->assertDatabaseHas('legacy_student_service_requests', ['id' => 17, 'user_id' => $student->id]);
        $this->assertDatabaseHas('student_service_requests', [
            'id' => 17,
            'student_id' => $student->id,
            'service_type' => 'id_card',
            'status' => 'pending',
            'student_comment' => 'Нужна новая карта',
        ]);
        $this->assertTrue(Schema::hasColumn('student_service_requests', 'photo_path'));
    }
}
