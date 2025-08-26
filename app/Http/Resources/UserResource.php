<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'phone_number' => $this->phone_number,
            'role' => $this->getRoleNames()->first(), // Get the role name
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,

            // Conditionally load the lawyer profile only if it exists
            'lawyer_profile' => $this->whenLoaded('lawyerProfile'),

            // You can also load other relationships like city
            // 'city' => $this->whenLoaded('city'),
        ];
    }
}
