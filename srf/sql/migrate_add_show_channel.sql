-- Run once if you already created srf_items before show/channel columns existed.
-- mysql -u ... -p mediadb_ < sql/migrate_add_show_channel.sql

ALTER TABLE srf_items
  ADD COLUMN show_id VARCHAR(128) NULL DEFAULT NULL AFTER episode_id,
  ADD COLUMN show_title VARCHAR(512) NULL DEFAULT NULL AFTER show_id,
  ADD COLUMN channel_id VARCHAR(128) NULL DEFAULT NULL AFTER show_title,
  ADD COLUMN channel_title VARCHAR(512) NULL DEFAULT NULL AFTER channel_id;
