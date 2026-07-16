-- ============================================================
-- Fitness Management System — Database (schema only)
-- Kushal Ghimire, Puspa Kamal Gharti, Sanzal Ghimire
-- Creates DB `fitness` + 9 tables. No data inserted.
-- ============================================================

DROP DATABASE IF EXISTS fitness;
CREATE DATABASE fitness CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fitness;

-- 1. roles — Admin / Trainer / Member
CREATE TABLE roles (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 2. users — sabai accounts (member, trainer, admin sabai yahi)
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20),
  role_id       INT NOT NULL DEFAULT 3,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- 3. membership_plans — monthly / yearly planss
CREATE TABLE membership_plans (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80) NOT NULL,
  duration_type ENUM('monthly','yearly') NOT NULL,
  duration_days INT NOT NULL,
  price         DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB;

INSERT INTO membership_plans (name, duration_type, duration_days, price) VALUES
('Basic Monthly', 'monthly', 30, 1500.00),
('Premium Monthly', 'monthly', 30, 2500.00),
('Basic Yearly', 'yearly', 365, 15000.00),
('Premium Yearly', 'yearly', 365, 25000.00);

-- 4. memberships — kun member le kun plan liyo
CREATE TABLE memberships (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  plan_id    INT NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  status     ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
) ENGINE=InnoDB;

-- 5. trainer_profiles — trainer ko extra info
CREATE TABLE trainer_profiles (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL UNIQUE,
  specialization   VARCHAR(120),
  bio              TEXT,
  experience_years INT DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. member_trainers — admin le kun trainer kun member lai assign garyo
CREATE TABLE member_trainers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  member_id   INT NOT NULL,
  trainer_id  INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_member_trainer (member_id, trainer_id)
) ENGINE=InnoDB;

-- 7. time_slots — bookable sessions (capacity sahit)
CREATE TABLE time_slots (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  slot_date  DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  capacity   INT NOT NULL DEFAULT 10,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slot (slot_date, start_time, end_time)
) ENGINE=InnoDB;

-- 8. bookings — gym session / trainer appointment + status
CREATE TABLE bookings (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  time_slot_id INT NOT NULL,
  booking_type ENUM('gym_session','trainer_appointment') NOT NULL,
  trainer_id   INT DEFAULT NULL,
  status       ENUM('pending','approved','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE CASCADE,
  FOREIGN KEY (trainer_id)   REFERENCES users(id) ON DELETE SET NULL,
  -- yo line le double-booking rok cha: euta member, euta slot, euta type matra
  UNIQUE KEY uq_user_slot_type (user_id, time_slot_id, booking_type)
) ENGINE=InnoDB;

-- 9. workout_plans — personalized plan, admin/trainer le banaune
CREATE TABLE workout_plans (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  member_id   INT NOT NULL,
  title       VARCHAR(150) NOT NULL,
  details     TEXT NOT NULL,
  assigned_by INT NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;
