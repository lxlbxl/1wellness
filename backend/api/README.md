# Admin API Documentation (n8n Integration)

This API endpoint is designed to allow external automation tools (like n8n, Zapier, or custom scripts) to securely retrieve AND update data from the OJG Herbal Admin Backend.

## 1. Essentials

- **Base Endpoint:** `https://[YOUR_DOMAIN]/backend/api/n8n-export.php`
- **Authentication:** Header-based (`X-API-KEY`)
- **Response Format:** JSON

## 2. Authentication

You must provide your security key in the request header.

**Header Name:** `X-API-KEY`  
**Where to find your Key:**  
Open `backend/config.php` and look for the `N8N_API_KEY` constant.

```php
// backend/config.php
define('N8N_API_KEY', 'n8n_sk_...'); // <--- This is your key
```

## 3. Retrieving Data (GET)

**Method:** `GET`

| Parameter | Type | Required | Description | Default |
| :--- | :--- | :--- | :--- | :--- |
| `type` | string | **Yes** | The type of data to fetch. Options: `sales`, `assessments`, `users`. | `sales` |
| `limit` | int | No | Maximum number of records to return. | `100` |
| `page` | int | No | Page number for pagination. | `1` |
| `start_date` | date | No | Filter records after this date (YYYY-MM-DD). | Yesterday |
| `end_date` | date | No | Filter records before this date (YYYY-MM-DD). | Today |

### Example (cURL)
```bash
curl -X GET "https://[YOUR_DOMAIN]/backend/api/n8n-export.php?type=sales&limit=50" \
     -H "X-API-KEY: [YOUR_KEY]"
```

## 4. Updating Data (POST)

**Method:** `POST`  
**Body:** JSON

You can update records in `assessments`, `users`, or `sales`.

### JSON Structure
```json
{
    "type": "assessments", 
    "id": 123,
    "data": {
        "status": "contacted",
        "notes": "Called user via n8n automation"
    }
}
```

| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `type` | string | **Yes** | Table to update: `assessments`, `users`, `sales` |
| `id` | int | **Yes** | The ID of the record to update |
| `data` | object | **Yes** | Key-value pairs of columns to update |

### Example (cURL)
```bash
curl -X POST "https://[YOUR_DOMAIN]/backend/api/n8n-export.php" \
     -H "X-API-KEY: [YOUR_KEY]" \
     -H "Content-Type: application/json" \
     -d '{
           "type": "assessments",
           "id": 105,
           "data": { "status": "processed" }
         }'
```

## 5. n8n Configuration

### For GET (Read)
1.  **Node:** HTTP Request
2.  **Method:** `GET`
3.  **URL:** `.../n8n-export.php`
4.  **Headers:** `X-API-KEY` : `[YOUR_KEY]`
5.  **Query Parameters:** `type` : `sales`

### For POST (Update)
1.  **Node:** HTTP Request
2.  **Method:** `POST`
3.  **URL:** `.../n8n-export.php`
4.  **Headers:** `X-API-KEY` : `[YOUR_KEY]`
5.  **Body Content Type:** `JSON`
6.  **Body Parameters:**
    *   `type`: `assessments`
    *   `id`: `{{ $json.id }}`
    *   `data`: `{ "status": "contacted" }` (Use an expression to build the object)
