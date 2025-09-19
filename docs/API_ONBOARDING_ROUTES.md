# Legal Connect API - Onboarding Routes Documentation

## Base URL
```
https://your-domain.com/api
```

## Authentication
All onboarding endpoints require authentication via Sanctum token and the user must have the `lawyer` role.

Include the token in the Authorization header:
```
Authorization: Bearer {your-token}
```

---

## Onboarding Endpoints

### 1. Get Onboarding Status
**GET** `/lawyer/onboarding/status`

Get the current onboarding status and progress for the authenticated lawyer.

**Response:**
```json
{
  "success": true,
  "data": {
    "overall_progress": 75,
    "completed_steps": 3,
    "total_steps": 4,
    "current_step": "documents",
    "steps": [
      {
        "name": "personal_info",
        "title": "Personal Information",
        "description": "Your contact details and office location",
        "order": 1,
        "required": true,
        "skippable": false,
        "icon": "user",
        "is_completed": true,
        "is_skipped": false,
        "completed_at": "2025-09-01T00:00:00Z"
      }
    ],
    "can_submit": false,
    "profile_status": "in_progress",
    "estimated_completion_time": "5 minutes"
  }
}
```

---

### 2. Get All Steps Metadata
**GET** `/lawyer/onboarding/steps`

Get metadata for all onboarding steps.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "name": "personal_info",
      "order": 1,
      "title": "Personal Information",
      "description": "Your contact details and office location",
      "required": true,
      "skippable": false,
      "icon": "user",
      "validation_rules": {
        "phone_number": "required|string|regex:/^(\\+234|0)[789][01]\\d{8}$/",
        "country_id": "required|exists:countries,id",
        "state_id": "required|exists:states,id",
        "city_id": "required|exists:cities,id",
        "office_address": "required|string|max:500",
        "bio": "nullable|string|max:1000"
      }
    }
  ]
}
```

---

### 3. Get Step Metadata
**GET** `/lawyer/onboarding/steps/{step}/metadata`

Get metadata for a specific step.

**Parameters:**
- `step` (string): Step name (e.g., "personal_info", "professional_info", "documents", "availability")

**Response:**
```json
{
  "success": true,
  "data": {
    "name": "personal_info",
    "order": 1,
    "title": "Personal Information",
    "description": "Your contact details and office location",
    "required": true,
    "skippable": false,
    "icon": "user",
    "validation_rules": {
      "phone_number": "required|string|regex:/^(\\+234|0)[789][01]\\d{8}$/",
      "country_id": "required|exists:countries,id",
      "state_id": "required|exists:states,id",
      "city_id": "required|exists:cities,id",
      "office_address": "required|string|max:500",
      "bio": "nullable|string|max:1000"
    }
  }
}
```

---

### 4. Get Step Validation Rules
**GET** `/lawyer/onboarding/steps/{step}/validation-rules`

Get validation rules for a specific step.

**Parameters:**
- `step` (string): Step name

**Response:**
```json
{
  "success": true,
  "data": {
    "phone_number": "required|string|regex:/^(\\+234|0)[789][01]\\d{8}$/",
    "country_id": "required|exists:countries,id",
    "state_id": "required|exists:states,id",
    "city_id": "required|exists:cities,id",
    "office_address": "required|string|max:500",
    "bio": "nullable|string|max:1000"
  }
}
```

---

### 5. Get Step Data
**GET** `/lawyer/onboarding/steps/{step}/data`

Get saved data for a specific step.

**Parameters:**
- `step` (string): Step name

**Response:**
```json
{
  "success": true,
  "data": {
    "step": "personal_info",
    "saved_data": {
      "phone_number": "+2348012345678",
      "country_id": 1,
      "state_id": 25,
      "city_id": 100,
      "office_address": "123 Legal Street, Victoria Island, Lagos",
      "bio": "Experienced lawyer with 10 years of practice"
    },
    "profile_data": {
      "phone_number": "+2348012345678",
      "country_id": 1,
      "state_id": 25,
      "city_id": 100,
      "office_address": "123 Legal Street, Victoria Island, Lagos",
      "bio": "Experienced lawyer with 10 years of practice"
    },
    "is_completed": true,
    "is_skipped": false
  }
}
```

---

### 6. Save Step Data
**POST** `/lawyer/onboarding/steps/{step}`

Save data for a specific step.

**Parameters:**
- `step` (string): Step name

**Request Body:**

For `personal_info`:
```json
{
  "phone_number": "+2348012345678",
  "country_id": 1,
  "state_id": 25,
  "city_id": 100,
  "office_address": "123 Legal Street, Victoria Island, Lagos",
  "bio": "Experienced lawyer with 10 years of practice"
}
```

For `professional_info`:
```json
{
  "nba_enrollment_number": "NBA/2015/123456",
  "year_of_call": 2015,
  "law_school": "Nigerian Law School, Lagos",
  "graduation_year": 2014,
  "practice_areas": [1, 2, 3],
  "specializations": [5, 8],
  "languages": [1, 2]
}
```

For `documents`:
```json
{
  "nba_certificate": "(file upload)",
  "cv": "(file upload)"
}
```

For `availability`:
```json
{
  "hourly_rate": 50000,
  "consultation_fee": 10000,
  "availability": {
    "monday": {
      "start": "09:00",
      "end": "17:00"
    },
    "tuesday": {
      "start": "09:00",
      "end": "17:00"
    },
    "wednesday": {
      "start": "09:00",
      "end": "17:00"
    },
    "thursday": {
      "start": "09:00",
      "end": "17:00"
    },
    "friday": {
      "start": "09:00",
      "end": "15:00"
    }
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Step saved successfully",
  "data": {
    "completed_step": "personal_info",
    "next_step": "professional_info",
    "overall_progress": 25,
    "can_submit": false
  }
}
```

---

### 7. Skip Step
**POST** `/lawyer/onboarding/steps/{step}/skip`

Skip an optional step.

**Parameters:**
- `step` (string): Step name (must be skippable)

**Request Body:**
```json
{
  "reason": "Will complete later"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Step skipped",
  "data": {
    "skipped_step": "availability",
    "next_step": null
  }
}
```

**Error Response (if step is required):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "step": ["Step 'personal_info' cannot be skipped as it is required"]
  }
}
```

---

### 8. Submit for Review
**POST** `/lawyer/onboarding/submit`

Submit the completed profile for admin review.

**Response:**
```json
{
  "success": true,
  "message": "Your profile has been submitted for review",
  "data": {
    "status": "pending_review",
    "estimated_review_time": "24-48 hours",
    "notification": "You will receive an email once your profile is reviewed"
  }
}
```

**Error Response (if not all required steps are completed):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "profile": [
      "Please complete all required steps before submitting.",
      "Missing steps: Professional Credentials, Document Upload"
    ]
  }
}
```

---

## Helper Endpoints

### Get Practice Areas
**GET** `/practice-areas`

Get list of all practice areas.

**Response:**
```json
[
  {
    "id": 1,
    "name": "Corporate Law",
    "description": "Business and corporate legal matters"
  },
  {
    "id": 2,
    "name": "Criminal Law",
    "description": "Criminal defense and prosecution"
  }
]
```

---

### Get Specializations
**GET** `/specializations`

Get list of all specializations.

**Response:**
```json
[
  {
    "id": 1,
    "name": "Mergers & Acquisitions",
    "practice_area_id": 1
  },
  {
    "id": 2,
    "name": "White Collar Crime",
    "practice_area_id": 2
  }
]
```

---

### Get Languages
**GET** `/languages`

Get list of all supported languages.

**Response:**
```json
[
  {
    "id": 1,
    "name": "English",
    "code": "en"
  },
  {
    "id": 2,
    "name": "Yoruba",
    "code": "yo"
  }
]
```

---

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden (Wrong Role)
```json
{
  "message": "User does not have the right roles."
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### 500 Server Error
```json
{
  "message": "Server error occurred",
  "error": "Error details (only in debug mode)"
}
```

---

## Onboarding Flow

1. **Start**: User registers as a lawyer
2. **Step 1**: Complete Personal Information
3. **Step 2**: Complete Professional Credentials
4. **Step 3**: Upload Documents (NBA Certificate, CV)
5. **Step 4**: Set Availability & Fees (Optional)
6. **Submit**: Submit profile for review
7. **Review**: Admin reviews and approves/rejects profile
8. **Complete**: Lawyer can start accepting clients

## Events Triggered

- `OnboardingStepCompleted`: Fired after each step completion
- `OnboardingCompleted`: Fired when profile is submitted for review

## Notes

- All file uploads should be sent as multipart/form-data
- Maximum file size: 5MB
- Accepted formats:
  - NBA Certificate: PDF, JPG, JPEG, PNG
  - CV: PDF, DOC, DOCX
- Phone numbers must be Nigerian format
- All timestamps are in ISO 8601 format
- Availability times are in 24-hour format (HH:mm)
