package id.sanad.layar;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.KeyEvent;
import android.view.View;
import android.view.WindowManager;
import android.webkit.RenderProcessGoneDetail;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;

/**
 * WebView layar penuh yang membuka /display. Semua logika ada di halaman; di sini hanya menjaga layar tetap hidup.
 */
public class MainActivity extends Activity {
    private static final String PREFS = "sanad";
    private static final String KEY_SERVER = "server";
    private static final long WATCHDOG_MS = 60_000;
    private static final long RETRY_MS = 15_000;

    private WebView web;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private long lastProgress = System.currentTimeMillis();
    private long okPressedAt = 0;

    private final Runnable watchdog = new Runnable() {
        @Override
        public void run() {
            // Halaman menulis judul tiap detik; bila lama tidak ada tanda hidup, muat ulang.
            if (System.currentTimeMillis() - lastProgress > WATCHDOG_MS * 3) {
                reload();
            }
            handler.postDelayed(this, WATCHDOG_MS);
        }
    };

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        createWebView();

        if (server().isEmpty()) {
            askServer();
        } else {
            reload();
        }

        handler.postDelayed(watchdog, WATCHDOG_MS);
    }

    private void createWebView() {
        web = new WebView(this);
        web.setSystemUiVisibility(View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION | View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY);
        WebSettings settings = web.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        web.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onReceivedTitle(WebView view, String title) {
                lastProgress = System.currentTimeMillis();
            }

            @Override
            public void onProgressChanged(WebView view, int progress) {
                lastProgress = System.currentTimeMillis();
            }
        });
        web.setWebViewClient(new WebViewClient() {
            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) {
                    handler.postDelayed(MainActivity.this::reload, RETRY_MS);
                }
            }

            @Override
            public boolean onRenderProcessGone(WebView view, RenderProcessGoneDetail detail) {
                // Proses peramban mati (sering di perangkat bermemori kecil): buat ulang dan lanjut.
                setContentView(new View(MainActivity.this));
                web.destroy();
                createWebView();
                reload();
                return true;
            }
        });
        setContentView(web);
    }

    private SharedPreferences prefs() {
        return getSharedPreferences(PREFS, MODE_PRIVATE);
    }

    private String server() {
        return prefs().getString(KEY_SERVER, "");
    }

    private void reload() {
        lastProgress = System.currentTimeMillis();
        String base = server().replaceAll("/+$", "");
        if (!base.isEmpty()) {
            web.loadUrl(base + "/display");
        }
    }

    private void askServer() {
        EditText input = new EditText(this);
        input.setText(server().isEmpty() ? "https://" : server());
        new AlertDialog.Builder(this)
            .setTitle("Alamat server SANAD")
            .setView(input)
            .setCancelable(!server().isEmpty())
            .setPositiveButton("Simpan", (dialog, which) -> {
                prefs().edit().putString(KEY_SERVER, input.getText().toString().trim()).apply();
                reload();
            })
            .show();
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_MENU) {
            askServer();
            return true;
        }
        if (keyCode == KeyEvent.KEYCODE_DPAD_CENTER || keyCode == KeyEvent.KEYCODE_ENTER) {
            if (event.getRepeatCount() == 0) {
                okPressedAt = System.currentTimeMillis();
            } else if (System.currentTimeMillis() - okPressedAt > 3000) {
                okPressedAt = Long.MAX_VALUE;
                askServer();
            }
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        web.destroy();
        super.onDestroy();
    }
}
