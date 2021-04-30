<?php
namespace Models;
use Illuminate\Database\Eloquent\Model;
class User extends Model
{
   /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $table = "users";
   /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'username', 'healthcheckuuid'
    ];

}