# Video Upload 400 Error - Fix Summary

## Issues Identified and Fixed

### 1. **Form Field Configuration - CRITICAL**
**File**: `AdminVideoUploadType.php`

**Issue**: The visibility field was missing `'expanded' => true` and `'multiple' => false` options, which could cause radio button options to not be properly rendered and submitted.

**Fix Applied**:
```php
->add('visibility', ChoiceType::class, [
    'label' => 'Visibilité',
    'mapped' => false,
    'choices' => [
        'Privé' => 'PRIVATE',
        'Public' => 'PUBLIC',
    ],
    'data' => 'PRIVATE',
    'expanded' => true,      // Added
    'multiple' => false,      // Added
    'help' => 'Privé par défaut',
])
```

### 2. **Form Submission Encoding - IMPORTANT**
**Files**: `templates/admin/videos/create.html.twig`, `templates/video/upload.html.twig`

**Issue**: Forms containing file uploads need explicit `enctype="multipart/form-data"` declaration.

**Fix Applied**:
```twig
{{ form_start(form, { attr: { id: 'videoUploadForm', method: 'POST', enctype: 'multipart/form-data' } }) }}
```

### 3. **JavaScript Form Action URL - IMPORTANT**
**Files**: JavaScript in both upload templates

**Issue**: Using `form.getAttribute('action')` returns the attribute string value, but if the form doesn't have an explicit action attribute, it returns null. Better to use `form.action` which uses the DOM property (returns current URL if no action specified).

**Fix Applied**:
```javascript
// Before
const actionUrl = form.getAttribute('action') || window.location.href;

// After  
const actionUrl = form.action || window.location.href;
```

### 4. **Enhanced Error Reporting**
**Files**: JavaScript in both upload templates

**Added HTTP status code in error messages**:
```javascript
progressText.textContent = 'Upload failed (HTTP ' + xhr.status + '). Please retry.';
```

**Added abort handler**:
```javascript
xhr.onabort = function () {
    progressText.textContent = 'Upload cancelled.';
};
```

## What Could Cause the Original 400 Error

The "400 Bad Request" error typically indicates one of these issues:

1. **Missing or Invalid CSRF Token** - Symfony requires valid CSRF tokens for form submissions
2. **Form Validation Failure** - File MIME type check, file size validation, or required fields missing
3. **Malformed Request** - Improper Content-Type or form encoding
4. **Invalid Form Data** - Field names not matching expected form structure

## How to Test the Fix

### 1. **Admin Video Upload**
```
URL: /admin/videos/create
1. Go to the create video form
2. Select a user from the dropdown
3. Enter a title and game type
4. Choose visibility (now should work as radio buttons)
5. Select an MP4 file (max 200MB)
6. Click "Upload Video"
7. Check browser console (F12 > Network tab) for any errors
```

### 2. **User Video Upload**
```
URL: /upload
1. Enter video title
2. Select game type
3. Choose visibility
4. Select an MP4 file (max 200MB)
5. Click "Upload"
6. Monitor progress bar
7. Check browser console for final status
```

## Debugging Steps if Issues Persist

1. **Check browser console** (F12):
   - Look for network errors in the Network tab
   - Check for JavaScript errors in Console tab
   - Look for specific HTTP response codes

2. **Verify Cloudinary credentials**:
   - Check `.env` file has valid `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`
   - Test Cloudinary configuration: `php tools/test_video_upload.php`

3. **Check file requirements**:
   - File format must be MP4
   - File size must be ≤ 200MB
   - MIME type must be `video/mp4`

4. **Check server logs**:
   - Linux/Mac: `tail -f var/log/dev.log`
   - Windows: Check `var/log/app_error.log`

5. **Run debug command** (if available):
   ```bash
   php bin/console debug:video-upload
   ```

6. **Check CSRF protection**:
   - Ensure form_end() is properly closing the form
   - CSRF token should be in form as hidden field
   - Look for `_token` field in form data

## Code Files Modified

1. `src/Form/AdminVideoUploadType.php` - Added visibility field options
2. `templates/admin/videos/create.html.twig` - Updated form and JavaScript
3. `templates/video/upload.html.twig` - Updated form and JavaScript

## Additional Resources

- [Symfony File Upload Documentation](https://symfony.com/doc/current/reference/forms/types/file.html)
- [Symfony CSRF Protection](https://symfony.com/doc/current/security/csrf.html)
- [Cloudinary PHP SDK Documentation](https://cloudinary.com/documentation/php_integration)
