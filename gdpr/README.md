# GDPR Compliance Documentation

This directory contains all GDPR compliance resources for the 1wellness website.

## Files Created

| File | Description |
|------|-------------|
| `privacy-policy.html` | Comprehensive privacy policy explaining data collection, usage, and user rights |
| `terms-of-service.html` | Terms of service governing use of the website |
| `cookie-policy.html` | Detailed cookie policy with cookie inventory |
| `data-processing-agreement.html` | DPA for customers (GDPR Article 28 compliance) |
| `data-retention-policy.html` | Policy defining data retention periods and procedures |
| `user-rights-request.html` | Form for users to exercise their GDPR rights |

## Components

| File | Description |
|------|-------------|
| `components/gdpr-form-consent.html` | Reusable consent component for forms |

## JavaScript

| File | Description |
|------|-------------|
| `js/gdpr-cookie-consent.js` | Cookie consent banner with granular preferences |

## Implementation Checklist

### Completed

- [x] Privacy Policy page with full GDPR Article 13 & 14 disclosures
- [x] Cookie Policy with complete cookie inventory
- [x] Terms of Service page
- [x] Data Processing Agreement (Article 28)
- [x] Data Retention Policy with retention schedule
- [x] User Rights Request form
- [x] Cookie consent banner with granular controls
- [x] Form consent component for assessments
- [x] Footer links on all pages

### How to Add Cookie Banner to Pages

Add this script before the closing `</body>` tag:

```html
<script src="js/gdpr-cookie-consent.js" defer></script>
```

### How to Add Consent Component to Forms

Include the consent component before your form's submit button:

```html
<!-- Include GDPR consent component -->
<script src="../components/gdpr-form-consent.html"></script>
```

Or copy the HTML from `components/gdpr-form-consent.html` directly into your form.

## GDPR Requirements Summary

### Lawful Basis for Processing

1. **Consent** - Marketing, non-essential cookies, health data
2. **Contract** - Order fulfillment, account management
3. **Legal Obligation** - Tax records, fraud prevention
4. **Legitimate Interest** - Analytics, security, customer support

### Special Category Data (Health Information)

Health data collected through assessments receives enhanced protection:
- Explicit consent required (GDPR Article 9)
- Encrypted in transit and at rest
- Access restricted to authorized personnel
- Never shared with third parties without explicit consent

### User Rights

Users can exercise the following rights:
1. Right of access (Article 15)
2. Right to rectification (Article 16)
3. Right to erasure (Article 17)
4. Right to restriction of processing (Article 18)
5. Right to data portability (Article 20)
6. Right to object (Article 21)
7. Right to withdraw consent (Article 7)

### Data Retention Schedule

| Data Type | Retention Period |
|-----------|-----------------|
| Account Data | Duration + 3 years |
| Order Data | 7 years |
| Health Assessment | 3 years |
| Marketing Preferences | Until withdrawal + 30 days |
| Cookie Data | Up to 2 years |
| Analytics | 26 months (anonymized) |

### Contact Information

- **Data Controller**: 1wellness
- **Contact Email**: support@1wellness.club
- **DPO Contact**: dpo@1wellness.club

## Testing

To verify GDPR compliance:

1. Visit the homepage and check for cookie consent banner
2. Navigate to any assessment form and verify consent checkboxes
3. Check footer links on all pages point to GDPR documents
4. Test the user rights request form

## Updates

This documentation should be reviewed:
- Annually
- When data processing activities change
- When new third-party services are added
- When GDPR guidance is updated