<?php

namespace APP\plugins\generic\preprintToJournal\classes\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use APP\plugins\generic\preprintToJournal\PreprintToJournalSchemaMigration;

class Service extends Model
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
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return PreprintToJournalSchemaMigration::generateTableName('services');
    }

    /**
     * Get the table associated with the model.
     */
    public function getStatusResponse(): string
    {
        return match($this->status) {
            static::STATUS_PENDING => 'plugins.generic.preprintToJournal.service.status.response.pending',
            static::STATUS_AUTHORIZED   => 'plugins.generic.preprintToJournal.service.status.response.authorized',
            static::STATUS_UNAUTHORIZED     => 'plugins.generic.preprintToJournal.service.status.response.rejected',
        };
    }

    /**
     * Get the table associated with the model.
     */
    public function hasRegistered(): bool
    {
        return $this->registered_at ? true: false;
    }

    public function hasAuthorized(): bool
    {
        return $this->status === static::STATUS_AUTHORIZED;
    }

}
