<?php

namespace APP\plugins\generic\preprintToJournal\classes\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use APP\plugins\generic\preprintToJournal\PreprintToJournalSchemaMigration;

class RemoteService extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 1;
    public const STATUS_AUTHORIZED = 2;
    public const STATUS_UNAUTHORIZED = 3;

    /**
     * Model's primary key
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are not mass assignable.
     *
     * @var string[]|bool
     */
    protected $guarded = [];

    /**
     * Model's database table
     *
     * @var string
     */
    protected $table = 'preprint_to_journal_remote_services';

    /**
     * Get the table associated with the model.
     */
    public function getStatusResponse(): string
    {
        return match($this->status) {
            static::STATUS_PENDING          => 'plugins.generic.preprintToJournal.service.status.response.pending',
            static::STATUS_AUTHORIZED       => 'plugins.generic.preprintToJournal.service.status.response.authorized',
            static::STATUS_UNAUTHORIZED     => 'plugins.generic.preprintToJournal.service.status.response.rejected',
        };
    }

}
