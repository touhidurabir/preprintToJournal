<?php

namespace APP\plugins\generic\preprintToJournal;

use APP\core\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;

class PreprintToJournalSchemaMigration extends Migration
{
    protected const TABLE_PREFIX = 'preprint_to_journal_';

    public static function generateTableName(string $tableName): string
    {
        return static::TABLE_PREFIX . $tableName;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ( PreprintToJournalPlugin::isOJS() ) {

            Schema::create(static::generateTableName('remote_services'), function (Blueprint $table) {
                $table->unsignedBigInteger('id')->autoIncrement();
                $table->bigInteger('context_id');
                $table->unsignedBigInteger('remote_service_id');
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

            return;
        }

        Schema::create(static::generateTableName('services'), function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
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

        Schema::create(static::generateTableName('submissions'), function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->uuid();
            $table->unsignedBigInteger('service_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('remote_submission_id')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table
                ->foreign('submission_id', static::TABLE_PREFIX . 'submission_id')
                ->references('submission_id')
                ->on('submissions')
                ->onDelete('cascade');

            $table
                ->foreign('service_id', )
                ->references('id')
                ->on(static::generateTableName('services'))
                ->onDelete('cascade');
        });

        Schema::create(static::generateTableName('notifications'), function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('submission_id');
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
                ->foreign('submission_id')
                ->references('id')
                ->on(static::generateTableName('submissions'))
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (PreprintToJournalPlugin::isOJS()) {
            Schema::drop(static::generateTableName('remote_services'));
            return;
        }

        Schema::drop(static::generateTableName('services'));
        Schema::drop(static::generateTableName('notifications'));
        Schema::drop(static::generateTableName('submissions'));
    }
}