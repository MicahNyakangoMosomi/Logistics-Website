# Mashirikiano SACCO Website
- mashirikianosacco.co.ke

A modern, responsive website for Mashirikiano SACCO built with HTML, CSS, and JavaScript. This site serves as a digital front for the SACCO, providing information about its services, membership, and contact details.

## Features

- **Responsive Design**: Built with Bootstrap 5 for optimal viewing on all devices.
- **Modern UI**: Features a clean, professional design with smooth scrolling and animations.
- **Interactive Elements**: Includes a carousel for showcasing key messages and a contact form.
- **Easy Navigation**: Simple and intuitive navigation menu.

## Getting Started

### Prerequisites

- A modern web browser (Chrome, Firefox, Safari, Edge).
- [Bootstrap 5](https://getbootstrap.com/) (included via CDN).

### Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   ```
2. Open `index.php` in your web browser.

## Usage

- **Home**: Visit the homepage to see the latest updates and featured services.
- **About**: Learn about the history and mission of Mashirikiano SACCO.
- **Services**: Explore the various financial services offered.
- **Membership**: Find information on how to become a member.
- **Contact**: Get in touch with the SACCO through the contact form or WhatsApp integration.

## SACCO Member and M-Pesa System

New PHP modules support member authentication, M-Pesa C2B callback recording, contribution analytics, and admin reporting.

### Setup

1. Import `database/schema.sql` into MySQL.
2. Set these server environment variables:
   - `DB_HOST`
   - `DB_NAME` defaults to `mashirikianosacc_mashirikiano`
   - `DB_USER` defaults to `mashirikianosacc_mashirikianosacco`
   - `DB_PASS`
   - `ADMIN_REPORT_TOKEN` optional, protects `/admin` when set
   - `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`
3. Configure Safaricom Daraja C2B Confirmation URL to `https://your-domain.co.ke/api/callback.php`.

### Key URLs

- Member login: `/auth/login.php`
- Member dashboard: `/member/dashboard.php`
- C2B callback: `/api/callback.php`
- Admin members: `/admin/members.php`
- Admin reports: `/admin/reports.php`

### Callback Logic

The C2B callback treats `BillRefNumber` as `NationalID`, looks up `members.NationalID`, and saves the transaction with the matched `MemberID`. If no member exists, the transaction is still saved with `MemberID = NULL` for later reconciliation.

## Technologies Used

- **HTML5**: For the structure of the web pages.
- **CSS3**: For styling and layout.
- **Bootstrap 5**: For responsive design and UI components.
- **JavaScript**: For interactive elements and animations.

## License

This project is licensed under the MIT License.
