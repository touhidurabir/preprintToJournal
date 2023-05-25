<?php

declare(strict_types=1);

namespace APP\plugins\generic\preprintToJournal;

use APP\core\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class PreprintToJournalSchemaMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ( PreprintToJournalPlugin::isOJS() ) {
            Schema::create('preprint_to_journal_api_keys', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->autoIncrement();
                $table->bigInteger('user_id');
                $table->string('api_key')->unique();
                $table->timestamps();
                $table->softDeletes();

                $table
                    ->foreign('user_id')
                    ->references('user_id')
                    ->on('users')
                    ->onDelete('cascase');
            });

            return;
        }

        Schema::create('preprint_to_journal', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->timestamps();
            $table->softDeletes();

            $table
                ->foreign('context_id')
                ->references(Application::getContextDAO()->primaryKeyColumn)
                ->on(Application::getContextDAO()->tableName)
                ->onDelete('cascade');
            
            $table
                ->foreign('submission_id', 'preprint_to_journal_submission_id')
                ->references('submission_id')
                ->on('submissions')
                ->onDelete('cascade');
        });

        Schema::create('ldn_notification_mailbox', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('preprint_to_journal_id');
            $table->string('notification_id');
            $table->string('from_id');
            $table->text('told_to');
            $table->text('in_reply_told');
            $table->boolean('status');
            $table->json('payload');
            $table->string('direction');
            $table->timestamps();
            $table->softDeletes();

            $table
                ->foreign('preprint_to_journal_id')
                ->references('id')
                ->on('preprint_to_journal')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (PreprintToJournalPlugin::isOJS()) {
            Schema::drop('preprint_to_journal_api_keys');
            return;
        }

        Schema::drop('ldn_notification_mailbox');
        Schema::drop('preprint_to_journal');
    }
}