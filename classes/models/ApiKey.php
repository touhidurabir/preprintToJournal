<?php

namespace APP\plugins\generic\preprintToJournal\classes\models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiKey extends Model
{
    use SoftDeletes;

    /**
     * Model's database table
     *
     * @var string
     */
    protected $table = 'preprint_to_journal_api_keys';

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

    public function getApiKey(): ?string
    {
        return $this->api_key;
    }

    /**
     * Generate a secure unique API key.
     *
     * @return string
     */
    public static function generate(): string
    {
        do {
            $key = Str::random(40);
        } while (self::keyExists($key));

        return $key;
    }

    /**
     * Get ApiKey record by key value.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function getByKey(string $key): bool
    {
        return self::where('api_key', $key)->first();
    }

    /**
     * Check if a key already exists.
     *
     * Includes soft deleted records
     *
     * @param string $key
     *
     * @return bool
     */
    public static function keyExists(string $key): bool
    {
        return self::where('api_key', $key)
            ->withTrashed()
            ->first() instanceof self;
    }

}
