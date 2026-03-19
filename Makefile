.PHONY: deploy zip help

# thaigogym_plus の WP 環境へのパス
WP_PLUGINS_DIR := ../thaigogym_plus/html/wp-content/plugins
PLUGIN_NAME    := wp-site-pulse
DEST           := $(WP_PLUGINS_DIR)/$(PLUGIN_NAME)

# プラグインを WP プラグインディレクトリにコピー（クリーンコピー）
deploy:
	rm -rf $(DEST)
	mkdir -p $(DEST)/includes $(DEST)/admin/css $(DEST)/admin/js $(DEST)/admin/views $(DEST)/languages
	cp wp-site-pulse.php uninstall.php readme.txt $(DEST)/
	cp includes/*.php $(DEST)/includes/
	cp admin/*.php $(DEST)/admin/
	cp admin/views/*.php $(DEST)/admin/views/
	cp admin/css/*.css $(DEST)/admin/css/
	cp admin/js/*.js $(DEST)/admin/js/
	-cp languages/*.pot languages/*.po languages/*.mo $(DEST)/languages/ 2>/dev/null
	@echo "$(PLUGIN_NAME) を $(DEST)/ に配置しました"

# リリース用 ZIP を作成
zip:
	rm -f wp-site-pulse.zip
	zip -r wp-site-pulse.zip \
		wp-site-pulse.php uninstall.php readme.txt \
		includes/ admin/ languages/ \
		-x "*/.*" -x "*__MACOSX*"
	@echo "wp-site-pulse.zip を作成しました"

help:
	@echo "Site Pulse - Commands"
	@echo "========================"
	@echo "  make deploy  - プラグインを WP 環境にコピー"
	@echo "  make zip     - リリース用 ZIP を作成"
