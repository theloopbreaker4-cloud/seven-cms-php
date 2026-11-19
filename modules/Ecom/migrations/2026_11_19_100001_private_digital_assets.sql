-- Digital files live outside the web root (storage/private/digital/); media_id stays for old rows.
ALTER TABLE `ecom_digital_assets`
    ADD COLUMN IF NOT EXISTS `storage_path`  VARCHAR(255) NULL AFTER `media_id`,
    ADD COLUMN IF NOT EXISTS `original_name` VARCHAR(255) NULL AFTER `storage_path`,
    ADD COLUMN IF NOT EXISTS `size_bytes`    BIGINT UNSIGNED NULL AFTER `original_name`;
