# Season Request Management System

This system manages the complete workflow for student season requests, from initial request to payment collection and season issuance.

## Workflow

1. **Student Request** → Student submits a season request
2. **HOD Approval** → HOD approves or rejects the request
3. **Payment Collection** → Collect payment (student, SLGTI, CTB portions)
4. **Season Issuance** → Issue season after full payment
5. **Balance/Arrears** → Collect remaining balance or issue arrears

## Files Created

### Main Pages

1. **SeasonRequests.php** - Main listing page showing all season requests
   - Access: All logged-in users (students see only their requests)
   - Features: Filter by status, year; View request details

2. **RequestSeason.php** - Form for students to create/edit season requests
   - Access: Students (can edit only their own pending requests)
   - Features: Create new request, edit pending requests

3. **ApproveSeasonRequest.php** - HOD approval interface
   - Access: HOD, ADM
   - Features: Approve/reject pending requests with notes

4. **CollectSeasonPayment.php** - Payment collection form
   - Access: HOD, ADM, FIN, SAO
   - Features: Collect initial payment, update payment records
   - Supports: Student paid, SLGTI paid, CTB paid portions

5. **IssueSeason.php** - Season issuance form
   - Access: HOD, ADM, SAO
   - Features: Issue season after payment completion
   - Note: Only issues when balance is zero

6. **BalancePayment.php** - Balance payment and arrears management
   - Access: HOD, ADM, FIN, SAO
   - Features: Collect remaining balance payments, issue arrears

### Controller

7. **controller/SeasonRequestController.php** - AJAX controller for API operations
   - Endpoints:
     - `get_request_details` - Get request details by ID
     - `get_payment_summary` - Get payment information
     - `get_student_requests` - Get all requests for a student
     - `get_statistics` - Get dashboard statistics

## Database Tables

### season_requests
- Stores student season requests
- Status: pending, approved, rejected, cancelled
- Tracks approval by HOD

### season_payments
- Stores payment information
- Tracks: student_paid, slgti_paid, ctb_paid
- Calculates remaining_balance
- Status: Paid, Completed

## Access Control

- **Students (STU)**: Can create and view their own requests
- **HOD**: Can approve/reject, collect payments, issue seasons
- **Administration (ADM)**: Full access to all functions
- **Finance (FIN)**: Can collect payments and manage balances
- **Student Affairs Officer (SAO)**: Can collect payments, issue seasons, manage balance/arrears

## Usage

1. Navigate to `season/SeasonRequests.php` to see the main dashboard
2. Students: Click "New Season Request" to submit a request
3. HOD: Click "Approve Requests" to review and approve/reject
4. After approval: Click "Collect Payment" to record payment
5. After full payment: Click "Issue Season" to issue the season ticket
6. For outstanding balances: Use "Balance/Arrears" to collect or issue arrears

## Features

- ✅ Complete CRUD operations for season requests
- ✅ Role-based access control
- ✅ Payment tracking with multiple payment sources
- ✅ Balance calculation and tracking
- ✅ Arrears management
- ✅ Request approval workflow
- ✅ Responsive Bootstrap UI
- ✅ Form validation
- ✅ Error handling

## Notes

- The system automatically creates tables if they don't exist
- Payment status "Completed" indicates full payment received
- Season can only be issued when balance is zero
- All monetary values are stored as DECIMAL(10,2)

