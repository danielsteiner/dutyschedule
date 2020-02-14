<?php
namespace Models;
use Illuminate\Database\Eloquent\Model;
class Logs extends Model
{
   /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $table = "logs";
   /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'key', 'html', 'vevent'
    ];

}