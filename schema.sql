CREATE DATABASE IF NOT EXISTS car_workshop CHARACTER SET utf8mb4;
USE car_workshop;

CREATE TABLE IF NOT EXISTS mechanics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  max_cars TINYINT NOT NULL DEFAULT 4
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_name VARCHAR(100) NOT NULL,
  address VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  car_license VARCHAR(30) NOT NULL,
  car_engine VARCHAR(30) NOT NULL,
  appt_date DATE NOT NULL,
  mechanic_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (mechanic_id) REFERENCES mechanics(id),
  INDEX (appt_date, mechanic_id),
  INDEX (appt_date, phone),
  INDEX (appt_date, car_license)
) ENGINE=InnoDB;

INSERT INTO mechanics (name) VALUES
 ('Rahim Uddin'),('Karim Hossain'),('Jamal Ahmed'),('Sohel Rana'),('Nasir Khan');
