<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSheetsExport extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'period',
        'year',
        'week',
        'month',
        'quarter',
        'start_date',
        'end_date',
        'mode',
        'sheet',
        'deleted_rows',
        'written_rows',
        'status',
        'message',
        'request_params',
        'google_response',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'request_params' => 'array',
        'google_response' => 'array',
    ];
}
