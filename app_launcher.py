"""
Bubbles — Desktop App Launcher
Abre o site do Railway numa janela nativa.
Requer: pip install pywebview
"""

import webview

APP_NAME   = "Bubbles"
APP_URL    = "https://bubbles.up.railway.app/bubbles"
APP_WIDTH  = 1280
APP_HEIGHT = 800

def main():
    window = webview.create_window(
        title=APP_NAME,
        url=APP_URL,
        width=APP_WIDTH,
        height=APP_HEIGHT,
        min_size=(800, 600),
    )
    webview.start(private_mode=False)

if __name__ == "__main__":
    main()