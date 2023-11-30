<?php

namespace APP\plugins\generic\preprintToJournal;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
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
        Schema::create('preprint_to_journal_notifications', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->bigInteger('submission_id')->nullable();
            $table->string('notification_identifier');
            $table->string('from_identifier')->nullable();
            $table->text('told_to')->nullable();
            $table->text('in_reply_told')->nullable();
            $table->boolean('status')->default(1);
            $table->json('payload');
            $table->string('direction');
            $table->timestamps();
            $table->softDeletes();

            $table
                ->foreign('submission_id')
                ->references('submission_id')
                ->on('submissions')
                ->onDelete('set null');
        });

        if ( PreprintToJournalPlugin::isOJS() ) {

            Schema::create('preprint_to_journal_remote_services', function (Blueprint $table) {
                $table->bigInteger('id')->autoIncrement();
                $table->bigInteger('context_id');
                $table->bigInteger('remote_service_id');
                $table->string('name', 255)->nullable();
                $table->string('url', 255);
                $table->string('ip')->nullable();
                $table->integer('status')->unsigned()->default(1);
                $table->timestamp('response_at')->nullable();
                $table->bigInteger('responder_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
    
                $table
                    ->foreign('responder_id')
                    ->references('user_id')
                    ->on('users')
                    ->onDelete('set null');
            });

            Schema::create('preprint_to_journal_submissions', function (Blueprint $table) {
                $table->bigInteger('id')->autoIncrement();
                $table->bigInteger('service_id');
                $table->bigInteger('submission_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table
                    ->foreign('submission_id', 'preprint_to_journal_submission_id')
                    ->references('submission_id')
                    ->on('submissions')
                    ->onDelete('cascade');
    
                $table
                    ->foreign('service_id', )
                    ->references('id')
                    ->on('preprint_to_journal_remote_services')
                    ->onDelete('cascade');
            });

            return;
        }

        Schema::create('preprint_to_journal_services', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->bigInteger('remote_service_id')->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('url', 255);
            $table->string('ip')->nullable();
            $table->integer('status')->unsigned()->default(1);
            $table->boolean('active')->default(true);
            $table->timestamp('response_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->bigInteger('creator_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table
                ->foreign('context_id')
                ->references(Application::getContextDAO()->primaryKeyColumn)
                ->on(Application::getContextDAO()->tableName)
                ->onDelete('cascade');

            $table
                ->foreign('creator_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::create('preprint_to_journal_submissions', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->uuid();
            $table->bigInteger('service_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('remote_submission_id')->nullable();
            $table->text('payload')->nullable();
            $table->timestamp('transfered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table
                ->foreign('submission_id', 'preprint_to_journal_submission_id')
                ->references('submission_id')
                ->on('submissions')
                ->onDelete('cascade');

            $table
                ->foreign('service_id', )
                ->references('id')
                ->on('preprint_to_journal_services')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // disable the foreign key constrain
        
        if (PreprintToJournalPlugin::isOJS()) {
            Schema::drop('preprint_to_journal_remote_services');
            Schema::drop('preprint_to_journal_notifications');
            Schema::drop('preprint_to_journal_submissions');
            return;
        }

        Schema::drop('preprint_to_journal_services');
        Schema::drop('preprint_to_journal_notifications');
        Schema::drop('preprint_to_journal_submissions');

        DB::statement('SET FOREIGN_KEY_CHECKS=1;'); // enable the foreign key constrain
    }
}