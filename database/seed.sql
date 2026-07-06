-- Safe seed data for Anusarnsuntorn School website
-- Do not store real hosting passwords in this file.

SET NAMES utf8mb4;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('school_name_th', 'โรงเรียนโสตศึกษาอนุสารสุนทร'),
('school_name_en', 'Anusarnsuntorn School for the Deaf'),
('school_address', '6'),
('personnel_count', '85'),
('student_count', '196'),
('classroom_count', '29'),
('academic_year', '2569'),
('theme_color', 'blue')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Example only. Create the real admin user through your secure admin setup flow.
-- INSERT INTO `admin_users` (`username`, `password_hash`, `display_name`, `role`)
-- VALUES ('admin', '<replace-with-password_hash>', 'ผู้ดูแลระบบ', 'admin');
