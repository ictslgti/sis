-- Groups feature schema
CREATE TABLE IF NOT EXISTS groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  course_id VARCHAR(20) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_by VARCHAR(30),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Optional: module-wise staff assignment per group
CREATE TABLE IF NOT EXISTS group_staff_module (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  module_id VARCHAR(30) NOT NULL,
  staff_id VARCHAR(30) NOT NULL,
  role ENUM('LECTURER','INSTRUCTOR') NOT NULL,
  delivery_type ENUM('THEORY','PRACTICAL','BOTH') NOT NULL DEFAULT 'BOTH',
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  active TINYINT(1) DEFAULT 1,
  UNIQUE KEY uq_group_staff_module (group_id, module_id, staff_id, role, delivery_type),
  CONSTRAINT fk_group_staff_module_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  student_id VARCHAR(30) NOT NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','left') DEFAULT 'active',
  UNIQUE KEY uq_group_student (group_id, student_id),
  CONSTRAINT fk_group_students_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  staff_id VARCHAR(30) NOT NULL,
  role ENUM('LECTURER','INSTRUCTOR') NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  active TINYINT(1) DEFAULT 1,
  UNIQUE KEY uq_group_staff (group_id, staff_id, role),
  CONSTRAINT fk_group_staff_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  session_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  coverage_title VARCHAR(200) NOT NULL,
  coverage_notes TEXT NULL,
  created_by VARCHAR(30),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_group_sessions_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS group_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  student_id VARCHAR(30) NOT NULL,
  present TINYINT(1) NOT NULL,
  marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  marked_by VARCHAR(30),
  UNIQUE KEY uq_session_student (session_id, student_id),
  CONSTRAINT fk_group_attendance_session FOREIGN KEY (session_id) REFERENCES group_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
