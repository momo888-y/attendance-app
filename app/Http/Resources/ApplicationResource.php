<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * 修正申請を API 用の形にする
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'new_date' => $this->new_date->format('Y-m-d'),
            'new_clock_in' => $this->new_clock_in,
            'new_clock_out' => $this->new_clock_out,
            'comment' => $this->comment,
            'approval_status' => $this->approval_status,
            'application_date' => $this->application_date,
        ];
    }
}
