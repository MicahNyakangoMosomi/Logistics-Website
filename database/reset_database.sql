SET FOREIGN_KEY_CHECKS = 0;

-- 1. Clear all M-PESA contributions
DELETE FROM `contributions`;

-- 2. Clear all registered members
DELETE FROM `members`;

-- Optional: Clear old transactions table if it exists
-- DELETE FROM `transactions`;

-- Optional: Clear admin users (WARNING: You will not be able to login if you delete the only admin!)
-- DELETE FROM `admin_users`;

SET FOREIGN_KEY_CHECKS = 1;
