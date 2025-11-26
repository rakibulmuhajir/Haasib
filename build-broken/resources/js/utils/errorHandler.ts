/**
 * Global error handler for user-friendly error messages
 */

export interface ErrorResponse {
    message: string
    errors?: Record<string, string[]>
}

export interface UserFriendlyError {
    message: string
    field?: string
    type: 'validation' | 'authentication' | 'server' | 'network'
}

/**
 * Convert Laravel/backend errors to user-friendly messages
 */
export function transformError(error: any): UserFriendlyError {
    // Handle network errors
    if (!error.response) {
        return {
            message: 'Unable to connect to the server. Please check your internet connection and try again.',
            type: 'network'
        }
    }

    const status = error.response?.status
    const data = error.response?.data as ErrorResponse

    // Handle validation errors (422)
    if (status === 422 && data.errors) {
        const firstError = Object.values(data.errors)[0]?.[0]
        const field = Object.keys(data.errors)[0]
        
        // Transform specific validation messages
        if (firstError) {
            const userFriendlyMessage = transformValidationMessage(firstError, field)
            return {
                message: userFriendlyMessage,
                field,
                type: 'validation'
            }
        }
    }

    // Handle authentication errors (401, 403)
    if (status === 401) {
        return {
            message: 'Your session has expired. Please log in again.',
            type: 'authentication'
        }
    }

    if (status === 403) {
        return {
            message: 'You don\'t have permission to perform this action.',
            type: 'authentication'
        }
    }

    // Handle server errors (500, etc.)
    if (status >= 500) {
        return {
            message: 'Something went wrong on our end. Please try again in a moment.',
            type: 'server'
        }
    }

    // Handle too many requests (429)
    if (status === 429) {
        return {
            message: 'Too many attempts. Please wait a moment before trying again.',
            type: 'server'
        }
    }

    // Default error message
    return {
        message: data.message || 'Something went wrong. Please try again.',
        type: 'server'
    }
}

/**
 * Transform Laravel validation messages to user-friendly ones
 */
function transformValidationMessage(message: string, field?: string): string {
    const transformations: Record<string, string> = {
        // Email field errors
        'The email field is required.': 'Please enter your email address.',
        'The email must be a valid email address.': 'Please enter a valid email address.',
        'The email has already been taken.': 'This email is already registered. Please use a different email or try logging in.',
        
        // Username field errors
        'The username has already been taken.': 'This username is already taken. Please choose a different one.',
        'The username field is required.': 'Please enter a username.',
        'The username may only contain letters, numbers and underscores.': 'Username can only contain letters, numbers, and underscores.',
        
        // Password field errors
        'The password field is required.': 'Please enter a password.',
        'The password must be at least 8 characters.': 'Password must be at least 8 characters long.',
        'The password confirmation does not match.': 'Password confirmation does not match.',
        
        // Name field errors
        'The name field is required.': 'Please enter your full name.',
        'The name may only contain letters, spaces, hyphens, and dots.': 'Names can only contain letters, spaces, hyphens, and dots.',
        
        // Login field errors
        'The login field is required.': 'Please enter your email address or username.',
        'These credentials do not match our records.': 'Invalid email/username or password. Please check your credentials and try again.',
        
        // General database errors
        'SQLSTATE[23505]': 'This information is already registered. Please use different details.',
        'SQLSTATE[23502]': 'Required information is missing. Please fill in all required fields.',
        'SQLSTATE[23503]': 'Invalid reference. Please check your information.',
        
        // Account status errors
        'Your account is inactive.': 'Your account is inactive. Please contact support for assistance.',
    }

    // Check for exact matches first
    if (transformations[message]) {
        return transformations[message]
    }

    // Check for partial matches
    for (const [pattern, replacement] of Object.entries(transformations)) {
        if (message.includes(pattern.replace('The ', '').replace(' field', ''))) {
            return replacement
        }
    }

    // Handle duplicate key errors
    if (message.includes('duplicate key') || message.includes('already been taken') || message.includes('UNIQUE constraint')) {
        if (field === 'email') {
            return 'This email is already registered. Please use a different email or try logging in.'
        }
        if (field === 'username') {
            return 'This username is already taken. Please choose a different one.'
        }
        return 'This information is already registered. Please use different details.'
    }

    // Handle null constraint errors
    if (message.includes('null value') || message.includes('NOT NULL constraint')) {
        return 'Required information is missing. Please fill in all required fields.'
    }

    // Return the original message if no transformation is found
    return message
}

/**
 * Extract field name from Laravel validation error key
 */
export function extractFieldName(field: string): string {
    const fieldMappings: Record<string, string> = {
        'email': 'Email',
        'username': 'Username',
        'password': 'Password',
        'password_confirmation': 'Password Confirmation',
        'name': 'Name',
        'login': 'Email or Username'
    }

    return fieldMappings[field] || field.charAt(0).toUpperCase() + field.slice(1)
}