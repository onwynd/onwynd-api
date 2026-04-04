# Referral System API Integration Guide

## System Status: ✅ FULLY OPERATIONAL

The referral system is now fully functional and ready for frontend integration. Here's a comprehensive guide for connecting the frontend `http://localhost:3000/referral` page to the API.

## 🎯 Key Endpoints Available

### Ambassador System (for active ambassadors)
**Base URL:** `http://localhost:8000/api/v1/ambassador/`

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/dashboard` | GET | Get ambassador dashboard data | ✅ |
| `/stats` | GET | Get detailed ambassador statistics | ✅ |
| `/referral-code` | GET | Generate/get referral code | ✅ |
| `/referrals` | GET | Get ambassador's referral history | ✅ |
| `/payouts` | GET | Get payout history | ✅ |
| `/payouts` | POST | Request new payout | ✅ |

### Patient Referral System (for all patients)
**Base URL:** `http://localhost:8000/api/v1/referral/`

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/code` | GET | Get patient's referral code | ✅ |
| `/stats` | GET | Get patient's referral statistics | ✅ |
| `/list` | GET | Get patient's referral history | ✅ |
| `/leaderboard` | GET | Get referral leaderboard | ✅ |
| `/share/email` | POST | Share referral via email | ✅ |
| `/track-click` | POST | Track referral link click | ❌ |

### Admin Dashboard (for administrators)
**Base URL:** `http://localhost:8000/api/v1/admin/`

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/referrals` | GET | Get all referrals with filtering/pagination | ✅ |
| `/ambassadors` | GET | Get all ambassadors with filtering/pagination | ✅ |
| `/referrals/{id}` | GET | Get specific referral details | ✅ |
| `/ambassadors/{id}` | GET | Get specific ambassador details | ✅ |
| `/referrals/{id}/status` | PUT | Update referral status | ✅ |
| `/ambassadors/{id}/status` | PUT | Update ambassador status | ✅ |

## 🔧 Authentication Headers

All authenticated endpoints require:
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

## 📊 Sample API Responses

### Ambassador Dashboard
```json
{
  "success": true,
  "message": "Ambassador dashboard data retrieved.",
  "data": {
    "referral_code": "DKF3QH9D",
    "stats": {
      "total_referrals": 0,
      "active_referrals": 0,
      "total_earnings": 0
    },
    "recent_referrals": []
  }
}
```

### Ambassador Statistics
```json
{
  "success": true,
  "message": "Ambassador statistics retrieved successfully.",
  "data": {
    "total_referrals": 0,
    "successful_referrals": 0,
    "pending_referrals": 0,
    "total_earnings": 0,
    "referral_code": {
      "id": 1,
      "code": "DKF3QH9D",
      "expires_at": "2027-02-28T13:36:52.000000Z",
      "uses_count": 0,
      "max_uses": 100
    }
  }
}
```

### Referral Code Generation
```json
{
  "success": true,
  "message": "You already have a referral code.",
  "data": {
    "id": 1,
    "ambassador_id": 1,
    "code": "DKF3QH9D",
    "expires_at": "2027-02-28T13:36:52.000000Z",
    "uses_count": 0,
    "max_uses": 100
  }
}
```

## 🚀 Frontend Integration Steps

### 1. Check if User is Ambassador
```javascript
// Check if user has ambassador profile
const checkAmbassadorStatus = async (token) => {
  try {
    const response = await fetch('http://localhost:8000/api/v1/ambassador/dashboard', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    if (response.ok) {
      return { isAmbassador: true, data: await response.json() };
    } else if (response.status === 403) {
      return { isAmbassador: false };
    }
  } catch (error) {
    console.error('Error checking ambassador status:', error);
    return { isAmbassador: false };
  }
};
```

### 2. Display Ambassador Dashboard
```javascript
const loadAmbassadorDashboard = async (token) => {
  const response = await fetch('http://localhost:8000/api/v1/ambassador/dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  
  const data = await response.json();
  
  // Display referral code
  document.getElementById('referral-code').textContent = data.data.referral_code;
  
  // Display stats
  document.getElementById('total-referrals').textContent = data.data.stats.total_referrals;
  document.getElementById('total-earnings').textContent = `$${data.data.stats.total_earnings}`;
  
  // Generate referral link
  const referralLink = `http://localhost:3000/join/${data.data.referral_code}`;
  document.getElementById('referral-link').textContent = referralLink;
};
```

### 3. Handle Patient Referral View
```javascript
const loadPatientReferralView = async (token) => {
  // Get referral code
  const codeResponse = await fetch('http://localhost:8000/api/v1/referral/code', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  
  if (codeResponse.ok) {
    const codeData = await codeResponse.json();
    // User is an ambassador, show their referral code
    displayAmbassadorReferral(codeData.data);
  } else {
    // User is not an ambassador, show application form
    displayAmbassadorApplicationForm();
  }
};
```

### 4. Apply to Become Ambassador
```javascript
const applyAsAmbassador = async (token, applicationData) => {
  const response = await fetch('http://localhost:8000/api/v1/ambassador/apply', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(applicationData)
  });
  
  return response.json();
};
```

## 🎨 UI Components to Implement

### Ambassador Dashboard
- [ ] Referral code display with copy button
- [ ] Referral statistics cards
- [ ] Recent referrals table
- [ ] Earnings summary
- [ ] Payout request button

### Patient Referral View
- [ ] Referral code display (if ambassador)
- [ ] Ambassador application form (if not ambassador)
- [ ] Referral leaderboard
- [ ] Share buttons (email, social media)

### Admin Dashboard
- [ ] Referrals management table with filters
- [ ] Ambassador management table with status controls
- [ ] Referral statistics and analytics
- [ ] Reward management interface

## ⚠️ Important Notes

1. **Authentication**: All endpoints require valid user authentication tokens
2. **Role-based Access**: Different endpoints require different user roles
3. **Rate Limiting**: Implement proper rate limiting for API calls
4. **Error Handling**: Handle 401 (unauthenticated) and 403 (unauthorized) responses appropriately
5. **Data Refresh**: Refresh dashboard data periodically to show real-time statistics

## 🔍 Testing Results

✅ **Ambassador System**: Fully functional
✅ **Patient Referral System**: Fully functional  
✅ **Admin Dashboard**: Accessible (requires admin authentication)
✅ **Referral Code Generation**: Working
✅ **Statistics Retrieval**: Working
✅ **Dashboard Data**: Working

The system is now ready for frontend integration!