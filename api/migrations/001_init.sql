CREATE TYPE user_role AS ENUM ('user', 'moderator', 'admin');
CREATE TYPE vote_type AS ENUM ('like', 'dislike');

CREATE TABLE IF NOT EXISTS users (
  id            SERIAL       PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          user_role    NOT NULL DEFAULT 'user',
  avatar_url    VARCHAR(500),
  is_blocked    BOOLEAN      NOT NULL DEFAULT FALSE,
  is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

CREATE TABLE IF NOT EXISTS books (
  id             SERIAL       PRIMARY KEY,
  title          VARCHAR(300) NOT NULL,
  author         VARCHAR(200) NOT NULL,
  description    TEXT,
  genre          VARCHAR(100),
  cover_emoji    VARCHAR(10)  NOT NULL DEFAULT '📚',
  cover_url      VARCHAR(500),
  isbn           VARCHAR(20)  UNIQUE,
  published_year INT,
  created_by_id  INT          REFERENCES users(id) ON DELETE SET NULL,
  created_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at     TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS comments (
  id         SERIAL      PRIMARY KEY,
  text       TEXT        NOT NULL,
  is_deleted BOOLEAN     NOT NULL DEFAULT FALSE,
  is_flagged BOOLEAN     NOT NULL DEFAULT FALSE,
  user_id    INT         NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
  book_id    INT         NOT NULL REFERENCES books(id)    ON DELETE CASCADE,
  parent_id  INT                  REFERENCES comments(id) ON DELETE CASCADE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_comments_book   ON comments(book_id);
CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments(parent_id);

CREATE TABLE IF NOT EXISTS comment_votes (
  id         SERIAL      PRIMARY KEY,
  vote_type  vote_type   NOT NULL,
  user_id    INT         NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
  comment_id INT         NOT NULL REFERENCES comments(id) ON DELETE CASCADE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE(user_id, comment_id)
);

CREATE TABLE IF NOT EXISTS ratings (
  id         SERIAL         PRIMARY KEY,
  value      NUMERIC(3,1)   NOT NULL CHECK (value >= 1 AND value <= 5),
  user_id    INT            NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  book_id    INT            NOT NULL REFERENCES books(id) ON DELETE CASCADE,
  created_at TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
  UNIQUE(user_id, book_id)
);

CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$ BEGIN NEW.updated_at=NOW(); RETURN NEW; END; $$ LANGUAGE plpgsql;
DO $$ BEGIN
  CREATE TRIGGER trg_users_upd    BEFORE UPDATE ON users    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
  CREATE TRIGGER trg_books_upd    BEFORE UPDATE ON books    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
  CREATE TRIGGER trg_comments_upd BEFORE UPDATE ON comments FOR EACH ROW EXECUTE FUNCTION set_updated_at();
  CREATE TRIGGER trg_ratings_upd  BEFORE UPDATE ON ratings  FOR EACH ROW EXECUTE FUNCTION set_updated_at();
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
