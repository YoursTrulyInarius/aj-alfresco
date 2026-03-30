# 🏪 A&J Alfresco — Rental Management System
**A State-of-the-Art, Mobile-Responsive Rental & Utility Management Solution**

---

## 💎 Project Overview
A&J Alfresco is a professional Web-Based Rental Management System designed to transition manual, paper-based food park administration into a digital, real-time ecosystem. The platform specializes in **Stall Management**, **Automated Tenant Billing**, and **API-Integrated Digital Payments**.

## 🚀 Tech Stack
*   **Backend**: PHP 8.x (Custom Architecture)
*   **Database**: MySQL (XAMPP Environment)
*   **Frontend**: Vanilla HTML5 / Modern Javascript
*   **Styling**: Premium Mobile-First CSS (Zero-Gap Fluid Layout)
*   **UI Components**: SweetAlert2 (Modals), Select2 (Smart Search), Google Fonts (Outfit/Inter)
*   **Payments**: Official PayMongo Checkout API (E-Wallets)

---

## 🌊 Process Flows (Step-by-Step)

### 1. 🏢 Stall & Asset Creation
*   **Process**: Admin navigates to **Stalls**.
*   **Flow**: Admin creates a "Stall Entity" (e.g., S-001). This status is set to `vacant` by default.
*   **Logic**: A stall is the "Anchor" of the system. Without a stall, a contract cannot be generated.

### 2. 👥 Tenant Registration
*   **Process**: Admin navigates to **Tenants**.
*   **Flow**: Admin fills out the **Registration Form** (Interactive Modal).
*   **Logic**: System creates a unique `User ID` with `role='tenant'`. The tenant's security deposit is managed via the subsequent contract.

### 3. 📄 Contract Implementation
*   **Process**: Admin navigates to **Contracts**.
*   **Flow**: Admin links a **Tenant** to a **Vacant Stall**.
*   **Logic**: Admin defines the **Monthly Rent**, **Security Deposit**, and **Start/End Dates**. Once saved, the Stall status automatically flips to `occupied`.

### 4. 💰 The Payment Lifecycle (Official PayMongo)
*   **Process**: Tenant logs into their Dashboard.
*   **Flow**: 
    1.  Tenant clicks **"Make Payment"**.
    2.  System initializes a **PayMongo Checkout Session** via PHP.
    3.  Tenant is redirected to the **Official PayMongo UI** (GCash, PayMaya, GrabPay).
    4.  Upon authorization, the tenant is sent to a **Success Page**.
    5.  The system captures the `payment_id`, creates a **Digital Receipt**, and updates the payment ledger in real-time.

### 5. 🔔 Automated Notifications
*   **Process**: Background polling/Dashboard load.
*   **Flow**: The system monitors contract end-dates and monthly bill dates.
*   **Logic**: Tenants receive "Real-time Reminders" on their dashboard (vibrant SweetAlerts) for upcoming dues or expiring contracts.

---

## 🛠️ Core Functions & Code Structure
*   **`includes/functions.php`**: The "Brain" of the system. Handles database connection, sanitation, and global logic.
*   **`admin/`**: High-priority management directory for Stalls, Tenants, and Financial Reports.
*   **`tenant/`**: Client-facing portal for viewing contracts and initiating digital payments.
*   **`assets/css/style.css`**: The definitive **Mobile-First CSS**. Uses a Margin-Push vs. Overlay-Drawer logic for 100% responsiveness on all devices.

---

## 📈 Future Enhancements (Roadmap)
For future development phases, the following high-priority features are scheduled:

1.  **📏 Stall Specification Gallery**:
    *   Add **Squaremeter (sqm)** fields for every stall for precise space management.
    *   Implement an **Image Gallery** for each stall, allowing potential tenants to view the actual space online before visiting.
2.  **📱 Communication Triggers**:
    *   **SMTP Email**: Automatic PDF receipts sent to tenant emails after every payment.
    *   **SMS Integration**: Real-time due date reminders sent directly to tenant phone numbers.
3.  **💳 Expanded Payment Rails**:
    *   Implementation of **PayMongo Credit/Debit Card** processing alongside existing E-Wallets.

---
*Created with ❤️ for A&J Alfresco Rental Management.*
