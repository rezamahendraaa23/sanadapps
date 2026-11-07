package id.sanad.layar;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

/** Membuka layar sendiri saat TV selesai menyala. */
public class BootReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
            Intent open = new Intent(context, MainActivity.class);
            open.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            context.startActivity(open);
        }
    }
}
