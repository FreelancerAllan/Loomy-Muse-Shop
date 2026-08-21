"# Loomy-Muse-Shop" 
| Color                    | HEX         | RGB           | Use                   |
| ------------------------ | ----------- | ------------- | --------------------- |
| 🌲 **Deep Forest Green** | **#0D291B** | 13, 41, 27    | Main logo, text, yarn |
| 🌿 **Dark Olive Green**  | **#46513A** | 70, 81, 58    | Leaves/details        |
| 🤍 **Warm Ivory**        | **#F7F2E8** | 247, 242, 232 | Main background       |
| 🥛 **Soft Cream**        | **#FBF9F4** | 251, 249, 244 | Secondary background  |
| 🪵 **Warm Beige**        | **#D9C8A9** | 217, 200, 169 | Yarn/linework         |
| 🟤 **Muted Gold/Tan**    | **#B88A3B** | 184, 138, 59  | Dots, hearts, accents |
| 🪶 **Taupe**             | **#A99B82** | 169, 155, 130 | Subtle illustrations  |


| Use        | Color       | Feel                      |
| ---------- | ----------- | ------------------------- |
| Primary    | **#3F6656** | Sophisticated sage/forest |
| Dark text  | **#243D32** | Soft deep green           |
| Secondary  | **#789487** | Muted, feminine           |
| Background | **#F8F6F0** | Warm luxury               |
| Accent     | **#C9A98A** | Subtle warm beige/gold    |




Other beautiful options

1. #4B705F — Elegant & earthy
A little greener and more natural. Great if you want the brand to feel artisanal and organic.

2. #527A68 — Light & fresh
More youthful and approachable while still looking premium.

3. #668878 — Soft sage
Very pretty if you want a Pinterest/Instagram-style aesthetic, especially with cream backgrounds.

4. #355A4A — Luxury version
Still relatively dark, but considerably softer than #0D291B. This is ideal if you want to retain a strong, premium identity.



SQL

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

Generate the password hash once with:
<?php
echo password_hash('admin@1287#', PASSWORD_DEFAULT);
?>


AFTER RUNNING HASH.PHP

INSERT INTO admins (email, password)
VALUES ('chogelyn@gmail.com', 'PASTE_GENERATED_HASH_HERE');



CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    delivery_location TEXT NOT NULL,
    status ENUM('Pending', 'Paid', 'Delivered', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);


CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    size ENUM('Small', 'Medium', 'Large') NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_status ENUM('In Stock', 'Out of Stock') NOT NULL DEFAULT 'In Stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


