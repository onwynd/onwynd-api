/* Standalone SQLite migration for mood_logs table using sqlite3 */
const fs = require('fs');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();

const dbFile = path.resolve(__dirname, '../database/database.sqlite');

if (!fs.existsSync(dbFile)) {
  fs.mkdirSync(path.dirname(dbFile), { recursive: true });
  fs.writeFileSync(dbFile, '');
}

const db = new sqlite3.Database(dbFile);

function run(sql) {
  return new Promise((resolve, reject) => {
    db.run(sql, (err) => (err ? reject(err) : resolve()));
  });
}

function get(sql, params = []) {
  return new Promise((resolve, reject) => {
    db.get(sql, params, (err, row) => (err ? reject(err) : resolve(row)));
  });
}

(async () => {
  try {
    console.log('Using SQLite database:', dbFile);
    await run('PRAGMA foreign_keys = ON;');

    const exists = await get(
      "SELECT name FROM sqlite_master WHERE type='table' AND name='mood_logs';",
    );
    if (exists) {
      console.log("Table 'mood_logs' already exists. Nothing to do.");
      db.close();
      return;
    }

    const createTable = `
      CREATE TABLE mood_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        patient_id INTEGER NULL,
        mood_score INTEGER NULL,
        emotions TEXT NULL,
        notes TEXT NULL,
        activities TEXT NULL,
        sleep_hours NUMERIC NULL,
        weather_data TEXT NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL,
        CONSTRAINT fk_mood_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_mood_logs_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
      );
    `;
    await run(createTable);
    await run('CREATE INDEX idx_mood_logs_user_id ON mood_logs(user_id);');
    await run('CREATE INDEX idx_mood_logs_patient_id ON mood_logs(patient_id);');

    console.log("Table 'mood_logs' created successfully.");
    db.close();
  } catch (err) {
    console.error('Migration failed:', err.message || err);
    db.close();
    process.exitCode = 1;
  }
})();
