# E-File System REST API v1.0 Documentation

**Base URL:** `http://your-domain.com/e-file/api/v1/`

**API Version:** 1.0
**Date:** 2025-01-13
**Content-Type:** `application/json`
**Authorization:** Bearer Token

---

## Table of Contents

1. [Authentication](#authentication)
2. [Folders](#folders)
3. [Sub-Folders](#sub-folders)
4. [Document Types](#document-types)
5. [Files/Archives](#filesarchives)
6. [Users](#users)
7. [Search](#search)
8. [Statistics](#statistics)
9. [Backup](#backup)
10. [Uploads & Synchronization](#uploads--synchronization)
11. [Editor](#editor)
12. [Settings](#settings)
13. [Cleanup](#cleanup)
14. [User Groups & Permissions](#user-groups--permissions)
15. [Error Handling](#error-handling)
16. [Mobile App Integration](#mobile-app-integration)

---

## Authentication

### 1. Login
**Endpoint:** `POST /auth/login`

**Request Body:**
```json
{
  "username": "admin",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "user_group": 1,
      "last_login": "2025-01-13 10:30:00"
    },
    "token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
    "expires_in": 2592000
  },
  "timestamp": "2025-01-13 10:30:00"
}
```

### 2. Logout
**Endpoint:** `POST /auth/logout`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logout successful",
  "data": [],
  "timestamp": "2025-01-13 10:35:00"
}
```

### 3. Validate Token
**Endpoint:** `GET /auth/validate`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Token is valid",
  "data": {
    "user": { ... },
    "valid": true
  },
  "timestamp": "2025-01-13 10:36:00"
}
```

### 4. Refresh Token
**Endpoint:** `POST /auth/refresh`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "new_token_here",
    "expires_in": 2592000
  },
  "timestamp": "2025-01-13 10:37:00"
}
```

### 5. Change Password
**Endpoint:** `POST /auth/change-password`
**Authorization:** Required

**Request Body:**
```json
{
  "current_password": "oldpassword",
  "new_password": "newpassword"
}
```

---

## Folders

### 1. Get All Folders
**Endpoint:** `GET /folders`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Folders retrieved successfully",
  "data": {
    "folders": [
      {
        "id": 1,
        "name": "Financial Records",
        "description": "All financial documents",
        "sub_folder_count": 5,
        "file_count": 120,
        "created_at": "2025-01-01 00:00:00"
      }
    ],
    "total": 1
  },
  "timestamp": "2025-01-13 10:40:00"
}
```

### 2. Get Single Folder
**Endpoint:** `GET /folders/{id}`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Folder retrieved successfully",
  "data": {
    "folder": {
      "id": 1,
      "name": "Financial Records",
      "description": "All financial documents",
      "sub_folder_count": 5,
      "file_count": 120,
      "sub_folders": [ ... ]
    }
  },
  "timestamp": "2025-01-13 10:41:00"
}
```

### 3. Create Folder
**Endpoint:** `POST /folders`
**Authorization:** Required

**Request Body:**
```json
{
  "name": "New Folder",
  "description": "Folder description"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Folder created successfully",
  "data": {
    "folder": { ... }
  },
  "timestamp": "2025-01-13 10:42:00"
}
```

### 4. Update Folder
**Endpoint:** `PUT /folders/{id}`
**Authorization:** Required

**Request Body:**
```json
{
  "name": "Updated Folder Name",
  "description": "Updated description"
}
```

### 5. Delete Folder
**Endpoint:** `DELETE /folders/{id}`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Folder deleted successfully",
  "data": [],
  "timestamp": "2025-01-13 10:43:00"
}
```

---

## Sub-Folders

### 1. Get All Sub-Folders
**Endpoint:** `GET /sub-folders`
**Authorization:** Required

### 2. Get Sub-Folders by Parent Folder
**Endpoint:** `GET /sub-folders/by-folder?folder_id={folder_id}`
**Authorization:** Required

### 3. Get Single Sub-Folder
**Endpoint:** `GET /sub-folders/{id}`
**Authorization:** Required

### 4. Create Sub-Folder
**Endpoint:** `POST /sub-folders`
**Authorization:** Required

**Request Body:**
```json
{
  "name": "New Sub-Folder",
  "description": "Sub-folder description",
  "archive_document_folder_id": 1
}
```

### 5. Update Sub-Folder
**Endpoint:** `PUT /sub-folders/{id}`
**Authorization:** Required

### 6. Delete Sub-Folder
**Endpoint:** `DELETE /sub-folders/{id}`
**Authorization:** Required

---

## Document Types

### 1. Get All Document Types
**Endpoint:** `GET /document-types`
**Authorization:** Required

### 2. Get Single Document Type
**Endpoint:** `GET /document-types/{id}`
**Authorization:** Required

### 3. Create Document Type
**Endpoint:** `POST /document-types`
**Authorization:** Required

**Request Body:**
```json
{
  "name": "Invoice",
  "description": "Invoice documents"
}
```

### 4. Update Document Type
**Endpoint:** `PUT /document-types/{id}`
**Authorization:** Required

### 5. Delete Document Type
**Endpoint:** `DELETE /document-types/{id}`
**Authorization:** Required

---

## Files/Archives

### 1. Get All Files
**Endpoint:** `GET /files`
**Authorization:** Required

**Query Parameters:**
- `sub_folder_id` (optional): Filter by sub-folder
- `document_type` (optional): Filter by document type
- `completed` (optional): Filter by completion status (0 or 1)
- `limit` (optional): Results per page (default: 100)
- `offset` (optional): Pagination offset (default: 0)

**Example:** `GET /files?sub_folder_id=1&limit=50&offset=0`

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Files retrieved successfully",
  "data": {
    "files": [
      {
        "id": 1,
        "name": "Financial Report 2024",
        "description": "Annual financial report",
        "path": "allfiles/2025/01/abc123.pdf",
        "sub_folder_id": 1,
        "sub_folder_name": "Annual Reports",
        "folder_name": "Financial Records",
        "document_type": 1,
        "document_type_name": "Report",
        "document_date": "2024-12-31",
        "file_size": 1024000,
        "completed": 1,
        "editor_name": "admin",
        "created_at": "2025-01-13 10:00:00"
      }
    ],
    "total": 120,
    "limit": 50,
    "offset": 0
  },
  "timestamp": "2025-01-13 10:50:00"
}
```

### 2. Get Single File
**Endpoint:** `GET /files/{id}`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "File retrieved successfully",
  "data": {
    "file": {
      "id": 1,
      "name": "Financial Report 2024",
      "path": "allfiles/2025/01/abc123.pdf",
      "file_url": "http://your-domain.com/e-file/allfiles/2025/01/abc123.pdf",
      ...
    }
  },
  "timestamp": "2025-01-13 10:51:00"
}
```

### 3. Upload File
**Endpoint:** `POST /files/upload`
**Authorization:** Required
**Content-Type:** `multipart/form-data`

**Form Data:**
- `file` (required): PDF file to upload
- `sub_folder_id` (required): Sub-folder ID
- `document_type` (required): Document type ID
- `name` (optional): File name
- `description` (optional): File description
- `document_date` (optional): Document date (YYYY-MM-DD)

**cURL Example:**
```bash
curl -X POST \
  http://your-domain.com/e-file/api/v1/files/upload \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -F 'file=@/path/to/document.pdf' \
  -F 'sub_folder_id=1' \
  -F 'document_type=1' \
  -F 'name=My Document' \
  -F 'description=Document description'
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "File uploaded successfully",
  "data": {
    "file": {
      "id": 150,
      "name": "My Document",
      "path": "allfiles/2025/01/xyz789.pdf",
      "file_url": "http://your-domain.com/e-file/allfiles/2025/01/xyz789.pdf",
      ...
    }
  },
  "timestamp": "2025-01-13 10:55:00"
}
```

### 4. Download File
**Endpoint:** `GET /files/{id}/download`
**Authorization:** Required

**Response:** Binary PDF file with appropriate headers

### 5. Update File
**Endpoint:** `PUT /files/{id}`
**Authorization:** Required

**Request Body:**
```json
{
  "name": "Updated File Name",
  "description": "Updated description",
  "document_date": "2025-01-15",
  "completed": 1
}
```

### 6. Delete File
**Endpoint:** `DELETE /files/{id}`
**Authorization:** Required

---

## Users

**Note:** User endpoints require admin permissions.

### 1. Get All Users
**Endpoint:** `GET /users`
**Authorization:** Required (Admin)

### 2. Get Single User
**Endpoint:** `GET /users/{id}`
**Authorization:** Required (Admin)

### 3. Get Current User
**Endpoint:** `GET /users/me`
**Authorization:** Required

### 4. Create User
**Endpoint:** `POST /users`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "username": "newuser",
  "password": "password123",
  "user_group": 2
}
```

### 5. Update User
**Endpoint:** `PUT /users/{id}`
**Authorization:** Required (Admin)

### 6. Delete User
**Endpoint:** `DELETE /users/{id}`
**Authorization:** Required (Admin)

---

## Search

### Global Search
**Endpoint:** `GET /search?q={query}`
**Authorization:** Required

**Query Parameters:**
- `q` or `query` (required): Search term

**Example:** `GET /search?q=financial`

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Search completed successfully",
  "data": {
    "query": "financial",
    "results": {
      "files": [ ... ],
      "folders": [ ... ],
      "sub_folders": [ ... ],
      "document_types": [ ... ]
    },
    "total": 45
  },
  "timestamp": "2025-01-13 11:00:00"
}
```

---

## Statistics

### 1. Dashboard Statistics
**Endpoint:** `GET /stats?action=dashboard`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Dashboard statistics retrieved successfully",
  "data": {
    "totals": {
      "folders": 10,
      "sub_folders": 45,
      "files": 1250,
      "completed_files": 1100,
      "pending_files": 150,
      "document_types": 8,
      "users": 5
    },
    "activity": {
      "today": 15,
      "this_month": 340,
      "last_7_days": [
        { "date": "2025-01-07", "count": 12 },
        { "date": "2025-01-08", "count": 18 }
      ]
    },
    "files_by_type": [
      { "name": "Invoice", "count": 450 },
      { "name": "Report", "count": 320 }
    ]
  },
  "timestamp": "2025-01-13 11:05:00"
}
```

### 2. Recent Files
**Endpoint:** `GET /stats?action=recent-files&limit=20`
**Authorization:** Required

### 3. File Statistics
**Endpoint:** `GET /stats?action=file-stats`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "File statistics retrieved successfully",
  "data": {
    "size_stats": {
      "total_files": 1250,
      "total_size": 5368709120,
      "avg_size": 4294967,
      "max_size": 52428800,
      "min_size": 102400,
      "total_size_mb": 5120.00
    },
    "files_by_month": [ ... ],
    "top_contributors": [ ... ]
  },
  "timestamp": "2025-01-13 11:10:00"
}
```

---

## Backup

**Note:** All backup endpoints require admin permissions.

### 1. Get Backup History
**Endpoint:** `GET /backup/history`
**Authorization:** Required (Admin)

**Query Parameters:**
- `limit` (optional): Number of records (default: 50)
- `offset` (optional): Pagination offset (default: 0)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Backup history retrieved successfully",
  "data": {
    "backups": [
      {
        "id": 1,
        "backup_type": "database",
        "file_name": "2025-01-13_10-30-00_database_abc123.sql",
        "file_size": 15728640,
        "status": "completed",
        "progress": 100,
        "created_at": "2025-01-13 10:30:00"
      }
    ],
    "total": 25,
    "limit": 50,
    "offset": 0
  }
}
```

### 2. Get Backup Status
**Endpoint:** `GET /backup/{id}/status`
**Authorization:** Required (Admin)

### 3. Initiate Backup
**Endpoint:** `POST /backup/initiate`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "backup_type": "database"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Backup initiated",
  "data": {
    "backup_id": 26,
    "status": "initiated",
    "message": "Backup process started successfully"
  }
}
```

### 4. Download Backup
**Endpoint:** `GET /backup/{id}/download`
**Authorization:** Required (Admin)

**Response:** Binary file (SQL or ZIP) with appropriate headers

### 5. Delete Backup
**Endpoint:** `DELETE /backup/{id}`
**Authorization:** Required (Admin)

---

## Uploads & Synchronization

### 1. Get Incoming Uploads
**Endpoint:** `GET /uploads/incoming`
**Authorization:** Required

**Query Parameters:**
- `system` (optional): Filter by system
- `category` (optional): Filter by category
- `start_date` (optional): Filter by date range
- `end_date` (optional): Filter by date range
- `limit` (optional): Results per page
- `offset` (optional): Pagination offset

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Incoming uploads retrieved successfully",
  "data": {
    "uploads": [
      {
        "id": 1,
        "path": "allfiles/uploads/file.pdf",
        "uploaded_time": "2025-01-13 10:00:00",
        "uploaded_user": "admin",
        "system": "E-File System",
        "category": "General"
      }
    ],
    "total": 50,
    "filters": {
      "systems": [...],
      "categories": [...]
    }
  }
}
```

### 2. Upload File
**Endpoint:** `POST /uploads/upload`
**Authorization:** Required
**Content-Type:** `multipart/form-data`

**Form Data:**
- `myfile` (required): File to upload
- `system` (optional): System name
- `category` (optional): Category name

### 3. Process Synchronization
**Endpoint:** `POST /uploads/sync`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "password": "Tanzania",
  "limit": 50
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Synchronization completed successfully",
  "data": {
    "processed": 50,
    "added": 35,
    "skipped": 15,
    "errors": [],
    "total_files": 500
  }
}
```

### 4. Download Filtered Files
**Endpoint:** `POST /uploads/download-batch`
**Authorization:** Required

**Request Body:**
```json
{
  "system": "E-File System",
  "category": "General",
  "start_date": "2025-01-01",
  "end_date": "2025-01-31"
}
```

### 5. Get Upload Settings
**Endpoint:** `GET /uploads/settings`
**Authorization:** Required

---

## Editor

### 1. Get Next/Previous File
**Endpoint:** `GET /editor/next` or `GET /editor/previous`
**Authorization:** Required

**Query Parameters:**
- `last_id` (optional): Last edited file ID

**Response (200 OK):**
```json
{
  "success": true,
  "message": "File retrieved successfully",
  "data": {
    "id": 150,
    "name": "Document Name",
    "document_type": 1,
    "description": "Document description",
    "year": 2025,
    "url": "allfiles/2025/01/doc.pdf",
    "number": "DOC-001",
    "payee_name": "John Doe",
    "sub_folder_id": 5,
    "document_date": "2025-01-13",
    "cheque_number": "CHQ-123",
    "duplicate": "0",
    "completed": "0"
  }
}
```

### 2. Get Current File
**Endpoint:** `GET /editor/{id}/current`
**Authorization:** Required

### 3. Save File Data
**Endpoint:** `POST /editor/save`
**Authorization:** Required

**Request Body:**
```json
{
  "id": 150,
  "document_type": 1,
  "year": 2025,
  "description": "Updated description",
  "number": "DOC-001",
  "sub_folder_id": 5,
  "payee_name": "John Doe",
  "document_date": "2025-01-13",
  "cheque_number": "CHQ-123",
  "completed": "1"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "File data saved successfully",
  "data": {
    "file_id": 150,
    "updated": true
  }
}
```

### 4. Get Editor Sub-Folders
**Endpoint:** `GET /editor/sub-folders`
**Authorization:** Required

**Response:** Returns sub-folders accessible to the user based on their group permissions

### 5. Get Editor Document Types
**Endpoint:** `GET /editor/document-types`
**Authorization:** Required

**Response:** Returns document types accessible to the user based on their group permissions

### 6. Create Sub-Folder (from Editor)
**Endpoint:** `POST /editor/sub-folder`
**Authorization:** Required

**Request Body:**
```json
{
  "name": "New Sub-Folder",
  "description": "Description",
  "archive_document_folder_id": 1
}
```

---

## Settings

### 1. Get Settings Menu
**Endpoint:** `GET /settings/menu`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Settings menu retrieved successfully",
  "data": {
    "menu": [
      {
        "name": "document_folders",
        "title": "Document Folders",
        "link": "folders",
        "description": "Add, Edit and Delete Document Folder/s",
        "icon": "folder"
      },
      ...
    ]
  }
}
```

### 2. Get System Settings
**Endpoint:** `GET /settings/system`
**Authorization:** Required (Admin)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "System settings retrieved successfully",
  "data": {
    "settings": {
      "app_name": "E-File System",
      "max_file_size": 52428800,
      "allowed_file_types": "pdf,jpg,jpeg,png,gif",
      "items_per_page": 25,
      "enable_notifications": 1,
      "enable_auto_backup": 0,
      "backup_frequency": "daily"
    }
  }
}
```

### 3. Update System Settings
**Endpoint:** `PUT /settings/system`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "max_file_size": 104857600,
  "enable_auto_backup": 1,
  "backup_frequency": "weekly"
}
```

### 4. Get User Preferences
**Endpoint:** `GET /settings/user-preferences`
**Authorization:** Required

**Response (200 OK):**
```json
{
  "success": true,
  "message": "User preferences retrieved successfully",
  "data": {
    "preferences": {
      "user_id": 1,
      "theme": "dark",
      "language": "en",
      "items_per_page": 25,
      "notifications_enabled": 1
    }
  }
}
```

### 5. Update User Preferences
**Endpoint:** `PUT /settings/user-preferences`
**Authorization:** Required

**Request Body:**
```json
{
  "theme": "light",
  "items_per_page": 50
}
```

---

## Cleanup

**Note:** All cleanup endpoints require specific permissions.

### 1. Analyze Unregistered Files
**Endpoint:** `GET /cleanup/unregistered-files`
**Authorization:** Required (FILE_DELETION permission)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Unregistered files analysis completed",
  "data": {
    "total_files_in_folder": 1250,
    "total_files_in_database": 1200,
    "unregistered_files": 50,
    "file_details": [
      {
        "name": "orphan_file.pdf",
        "size": 1024000,
        "modified": "2025-01-10 12:00:00",
        "mime": "application/pdf",
        "path": "allfiles/pf-archives/orphan_file.pdf"
      }
    ]
  }
}
```

### 2. Delete Unregistered File
**Endpoint:** `DELETE /cleanup/file`
**Authorization:** Required (FILE_DELETION permission)

**Request Body:**
```json
{
  "file_path": "allfiles/pf-archives/orphan_file.pdf"
}
```

### 3. Analyze Orphaned Records
**Endpoint:** `GET /cleanup/orphaned-records`
**Authorization:** Required (RECORD_DELETION permission)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Orphaned records analysis completed",
  "data": {
    "total_files_in_folder": 1200,
    "total_records_in_database": 1250,
    "orphaned_records": 50,
    "record_details": [
      {
        "id": 150,
        "name": "Missing File",
        "filename": "missing.pdf",
        "full_path": "allfiles/pf-archives/missing.pdf"
      }
    ]
  }
}
```

### 4. Delete Orphaned Record
**Endpoint:** `DELETE /cleanup/record/{id}`
**Authorization:** Required (RECORD_DELETION permission)

---

## User Groups & Permissions

**Note:** All user group endpoints require admin permissions.

### 1. Get All User Groups
**Endpoint:** `GET /user-groups`
**Authorization:** Required (Admin)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "User groups retrieved successfully",
  "data": {
    "user_groups": [
      {
        "id": 1,
        "name": "Administrators",
        "description": "System administrators",
        "member_count": 3,
        "created_at": "2025-01-01 00:00:00"
      }
    ]
  }
}
```

### 2. Get Single User Group
**Endpoint:** `GET /user-groups/{id}`
**Authorization:** Required (Admin)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "User group retrieved successfully",
  "data": {
    "user_group": {
      "id": 1,
      "name": "Administrators",
      "description": "System administrators",
      "members": [
        {
          "id": 1,
          "username": "admin",
          "name": "Admin User",
          "email": "admin@example.com"
        }
      ]
    }
  }
}
```

### 3. Create User Group
**Endpoint:** `POST /user-groups`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "name": "Editors",
  "description": "Document editors group"
}
```

### 4. Update User Group
**Endpoint:** `PUT /user-groups/{id}`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "name": "Senior Editors",
  "description": "Updated description"
}
```

### 5. Delete User Group
**Endpoint:** `DELETE /user-groups/{id}`
**Authorization:** Required (Admin)

### 6. Assign User to Group
**Endpoint:** `POST /user-groups/assign-user`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "user_id": 5,
  "group_id": 2
}
```

### 7. Get Group Folder Access
**Endpoint:** `GET /user-groups/{id}/folder-access`
**Authorization:** Required (Admin)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Group folder access retrieved successfully",
  "data": {
    "group_name": "Editors",
    "document_types": [
      {"folder_sub_id": 1, "name": "Invoice"}
    ],
    "folders": [
      {"folder_sub_id": 1, "name": "Financial Records"}
    ],
    "sub_folders": [
      {
        "folder_sub_id": 1,
        "name": "Annual Reports",
        "folder_name": "Financial Records"
      }
    ]
  }
}
```

### 8. Update Group Folder Access
**Endpoint:** `PUT /user-groups/{id}/folder-access`
**Authorization:** Required (Admin)

**Request Body:**
```json
{
  "document_types": [1, 2, 3],
  "folders": [1, 2],
  "sub_folders": [1, 2, 3, 4, 5]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Group folder access updated successfully",
  "data": {
    "group_id": 2,
    "access_rights_updated": 10
  }
}
```

---

## Error Handling

All error responses follow this format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": [ ... ],
  "timestamp": "2025-01-13 11:15:00"
}
```

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request succeeded |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid input |
| 401 | Unauthorized - Missing or invalid token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource doesn't exist |
| 405 | Method Not Allowed - Wrong HTTP method |
| 409 | Conflict - Duplicate resource |
| 500 | Internal Server Error - Server error |

---

## Mobile App Integration

### Authentication Flow

1. **Login Request:**
```javascript
POST /api/v1/auth/login
{
  "username": "user",
  "password": "pass"
}
```

2. **Store Token:**
```javascript
// Save token securely (e.g., AsyncStorage, SecureStore)
const token = response.data.token;
await SecureStore.setItemAsync('auth_token', token);
```

3. **Make Authenticated Requests:**
```javascript
fetch('http://your-domain.com/e-file/api/v1/folders', {
  method: 'GET',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  }
})
```

### File Upload Example (React Native)

```javascript
const uploadFile = async (fileUri, subFolderId, documentType) => {
  const token = await SecureStore.getItemAsync('auth_token');

  const formData = new FormData();
  formData.append('file', {
    uri: fileUri,
    type: 'application/pdf',
    name: 'document.pdf'
  });
  formData.append('sub_folder_id', subFolderId);
  formData.append('document_type', documentType);
  formData.append('name', 'My Document');

  const response = await fetch(
    'http://your-domain.com/e-file/api/v1/files/upload',
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'multipart/form-data'
      },
      body: formData
    }
  );

  return await response.json();
};
```

### Flutter Example

```dart
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  final Dio _dio = Dio();
  final storage = FlutterSecureStorage();
  static const String baseUrl = 'http://your-domain.com/e-file/api/v1';

  Future<void> login(String username, String password) async {
    final response = await _dio.post(
      '$baseUrl/auth/login',
      data: {
        'username': username,
        'password': password
      }
    );

    final token = response.data['data']['token'];
    await storage.write(key: 'auth_token', value: token);
  }

  Future<List> getFolders() async {
    final token = await storage.read(key: 'auth_token');

    final response = await _dio.get(
      '$baseUrl/folders',
      options: Options(
        headers: {'Authorization': 'Bearer $token'}
      )
    );

    return response.data['data']['folders'];
  }
}
```

### Best Practices

1. **Token Management:**
   - Store tokens securely
   - Refresh tokens before expiry
   - Clear tokens on logout

2. **Error Handling:**
   - Handle 401 errors (logout user)
   - Show user-friendly error messages
   - Implement retry logic for network errors

3. **File Uploads:**
   - Show upload progress
   - Handle large files appropriately
   - Validate file types before upload

4. **Offline Support:**
   - Cache frequently accessed data
   - Queue operations when offline
   - Sync when connection restored

---

## Testing the API

### Using cURL

**Login:**
```bash
curl -X POST http://your-domain.com/e-file/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'
```

**Get Folders:**
```bash
curl -X GET http://your-domain.com/e-file/api/v1/folders \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Using Postman

1. Create a new request
2. Set method (GET, POST, etc.)
3. Enter URL: `http://your-domain.com/e-file/api/v1/folders`
4. Add Authorization header: `Bearer YOUR_TOKEN`
5. For POST/PUT, add JSON body
6. Click Send

---

## Support

For questions or issues:
- Email: support@moinfo.co.tz
- Website: https://moinfo.co.tz

**API Version:** 1.0
**Last Updated:** 2025-01-13
