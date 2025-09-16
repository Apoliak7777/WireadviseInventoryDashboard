# WireadviseInventoryDashboard
# ENGLISH

## Wireadvise Inventory System

Wireadvise Inventory System is a simple yet powerful web-based warehouse management tool written in PHP (with MySQL), designed for small companies and technicians.  
It allows you to receive, assign, and track inventory items with QR code and photo support, optimized for both desktop and mobile devices.  
The system includes employee management, movement history, and login-protected admin features.

### Features

- Add, assign (take), and manage warehouse items
- Scan QR codes/barcodes to add or assign items
- Upload and view item photos (including direct camera capture from mobile)
- Track item movement with complete inventory history
- Manage employees/technicians
- Responsive design: works perfectly on both PC and mobile
- Secure admin login with logout functionality
- Search/filter in tables (optional extension)
- Easy install: PHP + MySQL

### How to use

1. **Clone this repository:**
    ```sh
    git clone https://github.com/YourUsername/Wireadvise-Inventory.git
    ```
2. **Import the database:**  
   Use the provided SQL file to create tables (see `db.sql`).  
   Make sure your MySQL user has access and set DB credentials in `db.php`.

3. **Set up uploads folder:**  
   Create an `uploads/` folder in the project root and make it writable.

4. **Run the app:**  
   Deploy to your webserver with PHP (Apache, nginx, etc).  
   Open the app in your browser.

5. **Login as Admin:**  
   Use the default credentials (username: `*`, password: `*`) to access admin features.

---


<img width="816" height="380" alt="image" src="https://github.com/user-attachments/assets/1737e638-dad7-4d7d-b237-c48dff7f32f7" />
