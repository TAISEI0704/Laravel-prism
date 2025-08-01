FROM postgres:17

RUN set -eux; \
    apt-get update && \
    # pgvector 0.8.0 (公式PGDG) をインストール
    apt-get install -y --no-install-recommends postgresql-17-pgvector && \
    # IVFFlat / HNSW 用プリロード設定
    mkdir -p /etc/postgresql/conf.d && \
    echo "shared_preload_libraries = 'vector'" > /etc/postgresql/conf.d/pgvector.conf && \
    # データベース初期化時にvector拡張を有効化
    mkdir -p /docker-entrypoint-initdb.d && \
    echo "CREATE EXTENSION IF NOT EXISTS vector;" > /docker-entrypoint-initdb.d/01_pgvector.sql && \
    # キャッシュクリーンアップ
    rm -rf /var/lib/apt/lists/*
