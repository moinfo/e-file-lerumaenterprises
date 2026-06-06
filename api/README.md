# E-File System REST API

A comprehensive RESTful API for the E-File System, enabling mobile and third-party integrations.

## Quick Start

### Base URL
```
http://your-domain.com/e-file/api/v1/
```

### Authentication

1. **Login to get token:**
```bash
curl -X POST http://localhost/e-file/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'
```

2. **Use token in subsequent requests:**
```bash
curl -X GET http://localhost/e-file/api/v1/folders \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## API Endpoints

| Resource | Endpoints |
|----------|-----------|
| **Authentication** | `/auth/login`, `/auth/logout`, `/auth/validate`, `/auth/refresh`, `/auth/change-password` |
| **Folders** | `/folders` (GET, POST, PUT, DELETE) |
| **Sub-Folders** | `/sub-folders` (GET, POST, PUT, DELETE) |
| **Document Types** | `/document-types` (GET, POST, PUT, DELETE) |
| **Files** | `/files` (GET, POST, PUT, DELETE), `/files/upload`, `/files/{id}/download` |
| **Users** | `/users` (GET, POST, PUT, DELETE) |
| **Search** | `/search?q={query}` |
| **Statistics** | `/stats?action=dashboard`, `/stats?action=recent-files`, `/stats?action=file-stats` |

## Features

✅ JWT-based authentication with token management
✅ Complete CRUD operations for all resources
✅ File upload and download support
✅ Global search across files, folders, and types
✅ Dashboard statistics and analytics
✅ User management with permissions
✅ CORS enabled for mobile apps
✅ RESTful design with proper HTTP methods
✅ JSON responses with consistent format
✅ Error handling with appropriate status codes

## Documentation

Full API documentation is available in `/api/v1/API_DOCUMENTATION.md`

## Testing

### Test Login
```bash
curl -X POST http://localhost/e-file/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"your_username","password":"your_password"}'
```

### Test API Info
```bash
curl -X GET http://localhost/e-file/api/v1/
```

## Mobile Integration

### React Native Example
```javascript
// Login
const login = async (username, password) => {
  const response = await fetch('http://your-domain/e-file/api/v1/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password })
  });
  const data = await response.json();
  return data.data.token;
};

// Get Folders
const getFolders = async (token) => {
  const response = await fetch('http://your-domain/e-file/api/v1/folders', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();
  return data.data.folders;
};
```

### Flutter Example
```dart
// Login
Future<String> login(String username, String password) async {
  final response = await http.post(
    Uri.parse('http://your-domain/e-file/api/v1/auth/login'),
    headers: {'Content-Type': 'application/json'},
    body: jsonEncode({'username': username, 'password': password})
  );
  final data = jsonDecode(response.body);
  return data['data']['token'];
}

// Get Folders
Future<List> getFolders(String token) async {
  final response = await http.get(
    Uri.parse('http://your-domain/e-file/api/v1/folders'),
    headers: {'Authorization': 'Bearer $token'}
  );
  final data = jsonDecode(response.body);
  return data['data']['folders'];
}
```

## Security

- ✅ Token-based authentication
- ✅ CORS configuration
- ✅ SQL injection prevention (parameterized queries)
- ✅ File type validation
- ✅ Permission checks
- ✅ Secure password hashing

## File Structure

```
api/
├── v1/
│   ├── index.php              # Main API router
│   ├── .htaccess              # URL rewriting rules
│   ├── API_DOCUMENTATION.md   # Full API documentation
│   └── endpoints/
│       ├── auth.php           # Authentication endpoints
│       ├── folders.php        # Folder management
│       ├── sub_folders.php    # Sub-folder management
│       ├── document_types.php # Document type management
│       ├── files.php          # File operations
│       ├── users.php          # User management
│       ├── search.php         # Search functionality
│       └── stats.php          # Statistics and analytics
└── README.md                  # This file
```

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... },
  "timestamp": "2025-01-13 10:00:00"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": [ ... ],
  "timestamp": "2025-01-13 10:00:00"
}
```

## Support

For questions or issues:
- **Email:** support@moinfo.co.tz
- **Website:** https://moinfo.co.tz
- **GitHub Issues:** [Report an issue]

## License

Copyright © 2025 MoinfoTech Company Limited. All rights reserved.

---

**API Version:** 1.0
**Last Updated:** 2025-01-13
