
-- Таблица для хранения векторных эмбеддингов

CREATE TABLE IF NOT EXISTS vector_embeddings (

    id INTEGER PRIMARY KEY AUTOINCREMENT,

    file_path TEXT NOT NULL,

    chunk_index INTEGER NOT NULL,

    chunk_text TEXT NOT NULL,

    embedding BLOB NOT NULL,

    embedding_model VARCHAR(50) NOT NULL,

    chunk_hash VARCHAR(64) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(file_path, chunk_index)

);



-- Метаданные файлов для отслеживания изменений

CREATE TABLE IF NOT EXISTS file_metadata (

    id INTEGER PRIMARY KEY AUTOINCREMENT,

    file_path TEXT UNIQUE NOT NULL,

    file_hash VARCHAR(64) NOT NULL,

    last_modified TEXT NOT NULL,

    file_size INTEGER NOT NULL,

    chunks_count INTEGER DEFAULT 0,

    last_vectorized TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);



-- Индексы для быстрого поиска

CREATE INDEX IF NOT EXISTS idx_vector_file_path ON vector_embeddings(file_path);

CREATE INDEX IF NOT EXISTS idx_vector_model ON vector_embeddings(embedding_model);

CREATE INDEX IF NOT EXISTS idx_vector_hash ON vector_embeddings(chunk_hash);

CREATE INDEX IF NOT EXISTS idx_file_metadata_path ON file_metadata(file_path);

CREATE INDEX IF NOT EXISTS idx_file_metadata_hash ON file_metadata(file_hash);

