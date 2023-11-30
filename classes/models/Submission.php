<?php

namespace APP\plugins\generic\preprintToJournal\classes\models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use APP\plugins\generic\preprintToJournal\PreprintToJournalSchemaMigration;

class Submission extends Model
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
     * Model's database table
     *
     * @var string
     */
    protected $table = 'preprint_to_journal_submissions';

}
