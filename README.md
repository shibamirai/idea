# Idea アプリケーション

Laravel From Scratch (2026 Edition) の最終プロジェクトを実際に実装しました。基本的にエピソードごとにコミットしていますので、git ログから各エピソードでの変更内容を確認することができます。

本編では [Laravel Forge](https://forge.laravel.com/) にデプロイするようになっていますが、Laravel Forge は有料のため、代わりに [Render](https://render.com/) の無料プラン (Hobby) でデプロイを行っています（デプロイしたアプリは[こちら](https://idea-zlkl.onrender.com/)）。そのため以下のような差異や制限があります。

- このプロジェクトの PHP バージョンは 8.4 ではなく **8.3** です
    > Laravel プロジェクトを Render にデプロイするには Docker で環境を用意する必要がありますが、そこで PHP 8.3 までの環境しか用意できなかったため。
- Node のバージョンは 22 です
    > 私のローカル開発環境で Node 22 を使用していたため Docker 環境もそれに合わせています。別のバージョンの場合は Dockerfile の修正が必要です。
- [Render にデプロイしたアプリ](https://idea-zlkl.onrender.com/)では画像アップロードができません
    > Render の無料枠では画像アップロードができないため、デプロイ先のアプリでは画像を選択したアイデアの登録は失敗（エラーは出ずに単に無視される）します。画像なしのアイデアの登録は可能です。
- ブラウザテスト用のフォルダは Browser には変更せず Feature のままにしています
    > 本編ではブラウザテスト用のフォルダを変更していますが、そこで指示された修正だけでは変更できないため Feature のままにしています。Episode 42 で Browser に変更できるようになっています。

## Render へのデプロイ方法

SQLite および Vite を使った Laravel アプリを Render へデプロイする方法です（このプロジェクトでの設定は[こちら](https://github.com/shibamirai/idea/commit/17f72af6e15b74e220030bdc92fab4271593905c)）。Laravel アプリは Render 上で Docker を使ってデプロイします。

### デプロイのためのアプリの修正

1. ブラウザでの混合コンテンツ警告を回避するために、全てのアセットを HTTPS で提供するようにします。app/Providers/AppServiceProvider.php を下記のように修正してください。

    ```php
    namespace App\Providers;

    use Illuminate\Routing\UrlGenerator;
    use Illuminate\Support\ServiceProvider;

    class AppServiceProvider extends ServiceProvider
    {
        // ...

        public function boot(UrlGenerator $url)
        {
            if (env('APP_ENV') == 'production') {
                $url->forceScheme('https');
            }
        }
    }
    ```

1. Docker の設定を行います。Laravel プロジェクトの直下に (1)Dockerfile (2).dockerignore (3)conf/nginx/nginx-site.confの３つのファイルを作成します。

    1. Dockerfile

        nginx-php-fpm をベースにした Dockerfile を作成します。インストールされる PHP などのバージョンは[こちら](https://github.com/richarvey/nginx-php-fpm)でご確認ください。Vite の実行には npm が必要なので、ここで npm もインストールしています。

        ```conf
        # 必要な npm のバージョンをインストールするため、node 環境の別のベースをあわせて使用する
        FROM node:22.22.1-alpine AS node

        FROM richarvey/nginx-php-fpm:latest

        COPY . .

        # node 環境でインストールしたコマンドをコピーしてくる
        COPY --from=node /usr/lib /usr/lib
        COPY --from=node /usr/local/lib /usr/local/lib
        COPY --from=node /usr/local/include /usr/local/include
        COPY --from=node /usr/local/bin /usr/local/bin

        # Image config
        ENV SKIP_COMPOSER 1
        ENV WEBROOT /var/www/html/public
        ENV PHP_ERRORS_STDERR 1
        ENV RUN_SCRIPTS 1
        ENV REAL_IP_HEADER 1

        # Laravel config
        ENV APP_ENV production
        ENV APP_DEBUG false
        ENV LOG_CHANNEL stderr

        # Allow composer to run as root
        ENV COMPOSER_ALLOW_SUPERUSER 1

        CMD ["/start.sh"]
        ```

    1. .dockerignore

        Dockerイメージに含めないファイルを指定します。

        ```conf
        /node_modules
        /public/hot
        /public/storage
        /storage/*.key
        /vendor
        .env
        .phpunit.result.cache
        Homestead.json
        Homestead.yaml
        npm-debug.log
        yarn-error.log
        ```

    1. nginx-site.conf

        NGINXの設定を記述します。conf/nginx/ディレクトリを作成し、そこに配置します。

        ```conf
        server {
            # Render provisions and terminates SSL
            listen 80;

            # Make site accessible from http://localhost/
            server_name _;

            root /var/www/html/public;
            index index.html index.htm index.php;

            # Disable sendfile as per https://docs.vagrantup.com/v2/synced-folders/virtualbox.html
            sendfile off;

            # Add stdout logging
            error_log /dev/stdout info;
            access_log /dev/stdout;

            # block access to sensitive information about git
            location /.git {
                deny all;
                return 403;
            }

            add_header X-Frame-Options "SAMEORIGIN";
            add_header X-XSS-Protection "1; mode=block";
            add_header X-Content-Type-Options "nosniff";

            charset utf-8;

            location / {
                try_files $uri $uri/ /index.php?$query_string;
            }

            location = /favicon.ico { access_log off; log_not_found off; }
            location = /robots.txt  { access_log off; log_not_found off; }

            error_page 404 /index.php;

            location ~* \.(jpg|jpeg|gif|png|css|js|ico|webp|tiff|ttf|svg)$ {
                expires 5d;
            }

            location ~ \.php$ {
                fastcgi_split_path_info ^(.+\.php)(/.+)$;
                fastcgi_pass unix:/var/run/php-fpm.sock;
                fastcgi_index index.php;
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                fastcgi_param SCRIPT_NAME $fastcgi_script_name;
                include fastcgi_params;
            }

            # deny access to . files
            location ~ /\. {
                log_not_found off;
                deny all;
            }

            location ~ /\.(?!well-known).* {
                deny all;
            }
        }
        ```

1. アプリの起動時に実行されるデプロイスクリプトを作成します。scripts ディレクトリを作成し、その中に 00-laravel-deploy.sh という名前で作成します。アプリの起動に必要な composer や Artisan のコマンドに加え、Vite のインストールと実行を行い、SQLite のデータベースファイルがアプリから読み書きできるようにパーミッションを変更します。

    ```sh
    #!/usr/bin/env bash
    echo "Running composer"
    # composer global require hirak/prestissimo
    composer install --no-dev --working-dir=/var/www/html

    echo "Caching config..."
    php artisan config:cache

    echo "Caching routes..."
    php artisan route:cache

    echo "Running migrations..."
    php artisan migrate --force

    echo "Viteのインストールと実行"
    npm install
    npm run build

    echo "データベースファイルのパーミッションを読み書き可に変更"
    chmod 777 database
    chmod 777 database/database.sqlite
    ```

### GitHub リポジトリへの登録

ここまでの変更を GitHub リポジトリにプッシュします。Render は GitHub リポジトリと連携してデプロイを行うので、まだ GitHub リポジトリを用意していない方はここで用意してプロジェクトをプッシュしておいてください。

### Render のアカウント作成

Render は GitHub と連携してデプロイを行うので、あらかじめ GitHub のアカウントを用意しておきましょう。あとから GitHub に連携させることもできますが、GitHub アカウント経由で Render のアカウントを作成した方が簡単です。

1. [https://render.com/](https://render.com/) にアクセスし、`Get Started for Free` をクリックして登録画面を開きます。

1. `Create an account` で GitHub を選択します。

    ※ GitHub のアカウントを作成してから日が浅いとうまく Render アカウントが作成できない場合があるようです。そのときは別の方法を選択して作成してください。その場合の GitHub との連携は Web Service 作成時に行うことになります。

1. GitHub にログインしていなければログインするよう求められるのでログインしてください。

1. デバイス認証を行います。メールを確認して認証コードを入力してください。

1. GitHub と連携します。`Authorize Render` をクリックしてください。

1. 登録されているメールアドレスを確認して、`Create Account' をクリックします。
\
1. 先ほどのメールアドレスに認証メールが送られているので、メールを開いて中のリンクをクリックします。

1. Render の利用に関するいくつかの質問のページが開きます。必須なのは最後の質問だけなので最低それだけ入力してから `Continue to Render` をクリックします。

    - What should we call you?
        - 自分の名前（ニックネーム）を入力します。
    - How will you primary use Render?
        - 利用目的を選択します。`For personal use`（個人利用）でいいでしょう。
    - What type of project are you building?
        - 作成するプロジェクトの種類を選びます。`Web app`
    - Where is your project hosted?
        - 現在プロジェクトを実行している場所を選択します。AWSやHerokuなどにデプロイしているならそれを選択します。ローカル環境で動かしているだけなら`Project running localy`、これから新しく作るのであれば`Starting a new Project`を選びます。

1. ダッシュボードが表示されれば登録完了です。

### デプロイ（Web Serviceの作成）

Render で Web Service を新規に作成してデプロイを行います。その際、Web Serviceの設定で下記の設定も行ってください。

1. Render.com にログインし、Dashboard を開きます。

1. `New Web Service`を選択します。

    または、画面右上の`+ New`から`Web Service`を選択します。

1. デプロイするプロジェクトの連携先を設定していきます。まずは `Git Provider` から `GitHub` を選択します。すでにデプロイしたことがある場合は、`Credentials` から `Configure GitHub` を選んでください。

1. ポップアップが出てくるので、そこで GitHub のアカウントに Render をインストールします。ここで `All repositories` を選択すると、GitHub に登録されているすべてのプロジェクトだけでなく今後登録するプロジェクトもすべてデプロイされるようになるので、ここでは `Only select repositories` を選択します。すると `Select repositories` というセレクトボックスが現れるので、そこからデプロイするプロジェクトを選択します。

1. 選択したプロジェクトがリストに表示されるので、それを選択して `Connect` をクリックします。

1. Web Service の各種設定を行います。設定後、`Deploy Web Service`をクリックするとデプロイが始まります。

    - Region：Singaporeを選択します。
    - Instance Type：Freeを選択します。
    - Language：`Docker`を選択します。
    - Environment Variables：下記の環境変数を追加します。

        | NAME_OF_VARIABLE | value |
        |---|---|
        |APP_KEY|`php artisan key:generate --show`の出力をコピーして貼り付けてください。'base64:'から始まるランダムな文字列です。|
        |DB_CONNECTION|sqlite|
        |DB_HOST|127.0.0.1|

1. デプロイ中は Dashboard の Overview で進行状況を確認できます。Status が 'Delployed' になればデプロイ完了です。Service name をクリックすると Web Service の詳細を見ることができます。

1. デプロイしたサイトは、Web Service の詳細の左上の方にある https: から始まるリンクから開くことができます。


