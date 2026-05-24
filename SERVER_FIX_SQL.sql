-- نفّذ هذا في phpMyAdmin → قاعدة gisbjquz_gis_db → تبويب SQL
-- يحل: Unknown column 'local_cached_path' + عدم ظهور الصور

ALTER TABLE `inspection_photos`
  ADD COLUMN `local_cached_path` VARCHAR(500) NULL AFTER `drive_notes_file_id`,
  ADD COLUMN `processed_cache_path` VARCHAR(500) NULL AFTER `local_cached_path`;
