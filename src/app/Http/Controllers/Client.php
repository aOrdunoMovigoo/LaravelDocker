<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'dni',
        'email',
        'phone',
        'country',
        'region',
        'commune',
        'address',
        'status',
        'whatsapp_media_id',
    ];

    // aplicando relacion de permisos por client
    public function clientPermissions()
    {
        return $this->hasMany('App\Permission')->orderBy('id');
    }

    public function clientServices()
    {
        return $this->hasMany('App\ServiceByClient')->orderBy('id');
    }
}
