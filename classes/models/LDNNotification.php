<?php

namespace APP\plugins\generic\preprintToJournal\classes\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use APP\plugins\generic\preprintToJournal\PreprintToJournalSchemaMigration;

class LDNNotification extends Model
{
    use SoftDeletes;

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
        return PreprintToJournalSchemaMigration::generateTableName('notifications');
    }

    

}
