<?php

namespace Miladev\LaravelSettings\Models;

use Miladev\LaravelSettings\Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory;

    /**
     * Make "key" column as primary
     * @var string
     */
    protected $primaryKey = 'key';

    /**
     * SetPrimary Key as string
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Set Primary Key as not incrementing value
     * @var bool
     */
    public $incrementing = false;

    /**
     * Disabled Laravel default timestamp in settings table.
     * @var bool
     */
    public $timestamps = false;

    /**
     * make the key, value and autoload fillable
     */
    protected $fillable = ['key', 'value', 'autoload'];

    /**
     * Overwrite default factory class
     *
     * @return SettingFactory
     */
    protected static function newFactory()
    {
        return SettingFactory::new();
    }

}
