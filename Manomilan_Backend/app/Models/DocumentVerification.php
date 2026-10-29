<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentVerification extends Model
{
    protected $table = 'document_verifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'profile_id',
        'identificationProof_status', 'identificationProof_remarks',
        'birthDateProof_status', 'birthDateProof_remarks',
        'addressProof_status', 'addressProof_remarks',
        'educationProof_status', 'educationProof_remarks',
        'professionProof_status', 'professionProof_remarks',
        'incomeProof_status', 'incomeProof_remarks',
        'casteCertificate_status', 'casteCertificate_remarks',
        'medicalFitnessCertificate_status', 'medicalFitnessCertificate_remarks',
        'selfieVerification_status', 'selfieVerification_remarks',
    ];

    /**
     * Get the profile that owns the verification entry.
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
