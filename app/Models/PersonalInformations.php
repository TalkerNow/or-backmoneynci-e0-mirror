<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalInformations extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'civility', 'first_name', 'maiden_name', 'last_name', 'birth_date', "martial_status", "children_number", "mobile_number", "office_number",
        "personal_address", "personal_zip_code", "personal_city", "personal_country", "society_address", "society_zip_code",
        'military_service', 'society_name',"society_city", "society_country", "user_id", 'notes'
    ];
}
