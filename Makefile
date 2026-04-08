.PHONY: deploy deploy-xserver zip help

# thaigogym_plus の WP 環境へのパス
WP_PLUGINS_DIR := ../thaigogym_plus/html/wp-content/plugins
PLUGIN_NAME    := site-pulse
DEST           := $(WP_PLUGINS_DIR)/$(PLUGIN_NAME)

# プラグインを WP プラグインディレクトリにコピー（クリーンコピー）
deploy:
	rm -rf $(DEST)
	mkdir -p $(DEST)/includes $(DEST)/admin/css $(DEST)/admin/js $(DEST)/admin/views $(DEST)/languages
	cp site-pulse.php uninstall.php readme.txt $(DEST)/
	cp includes/*.php $(DEST)/includes/
	cp admin/*.php $(DEST)/admin/
	cp admin/views/*.php $(DEST)/admin/views/
	cp admin/css/*.css $(DEST)/admin/css/
	cp admin/js/*.js $(DEST)/admin/js/
	-cp languages/*.pot languages/*.po languages/*.mo $(DEST)/languages/ 2>/dev/null
	@echo "$(PLUGIN_NAME) を $(DEST)/ に配置しました"

# リリース用 ZIP を作成（WordPress.org はトップレベルにプラグインディレクトリが必要）
zip:
	rm -f site-pulse.zip
	rm -rf /tmp/site-pulse
	mkdir -p /tmp/site-pulse/includes /tmp/site-pulse/admin/css /tmp/site-pulse/admin/js /tmp/site-pulse/admin/views /tmp/site-pulse/languages
	cp site-pulse.php uninstall.php readme.txt /tmp/site-pulse/
	cp includes/*.php /tmp/site-pulse/includes/
	cp admin/*.php /tmp/site-pulse/admin/
	cp admin/views/*.php /tmp/site-pulse/admin/views/
	cp admin/css/*.css /tmp/site-pulse/admin/css/
	cp admin/js/*.js /tmp/site-pulse/admin/js/
	-cp languages/*.pot languages/*.po languages/*.mo /tmp/site-pulse/languages/ 2>/dev/null
	cd /tmp && zip -r $(CURDIR)/site-pulse.zip site-pulse/ -x "*/.*" -x "*__MACOSX*"
	rm -rf /tmp/site-pulse
	@echo "site-pulse.zip を作成しました"

# XServer にデプロイ
deploy-xserver:
	scp -r site-pulse.php uninstall.php readme.txt includes/ admin/ languages/ \
		xserver:~/thaigolus.com/public_html/gym/wp-content/plugins/site-pulse/

help:
	@echo "Site Pulse - Commands"
	@echo "========================"
	@echo "  make deploy          - ローカル WP 環境にコピー"
	@echo "  make deploy-xserver  - XServer にデプロイ"
	@echo "  make zip             - リリース用 ZIP を作成"
