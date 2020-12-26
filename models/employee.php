<?php
namespace Models;
use Illuminate\Database\Eloquent\Model;
class Employee extends Model
{
   /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $table = "employees";
   /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'name', 'dnrs', 'url', 'employee_id', 'fetched'
    ];

    public function getDnrsAttribute($value) {
        return json_decode($value);
    }
    public function setDnrsAttribute($value) {
        $this->attributes["dnrs"] = json_encode($value);
    }

}