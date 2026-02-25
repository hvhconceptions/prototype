package com.heidi.bookingadmin

import android.Manifest
import android.content.ContentProviderOperation
import android.content.pm.PackageManager
import android.os.Bundle
import android.provider.ContactsContract
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import java.util.concurrent.Executors

class ClientListActivity : AppCompatActivity() {
    private val executor = Executors.newSingleThreadExecutor()
    private var pendingContactClient: ClientEntity? = null

    private val contactPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted ->
        val client = pendingContactClient
        pendingContactClient = null
        if (!granted) {
            Toast.makeText(this, "Contacts permission denied.", Toast.LENGTH_SHORT).show()
            return@registerForActivityResult
        }
        if (client != null) {
            saveClientToPhoneContacts(client)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_clients)

        val listView = findViewById<RecyclerView>(R.id.clientsList)
        val emptyView = findViewById<TextView>(R.id.emptyClients)
        val clearAllView = findViewById<TextView>(R.id.clearAllNotifications)

        NotificationState.clearUnread(this)
        NotificationManagerCompat.from(this).cancel(PushMessagingService.BADGE_NOTIFICATION_ID)

        lateinit var adapter: ClientAdapter
        adapter = ClientAdapter(
            onDelete = { client ->
                executor.execute {
                    ClientDatabase.getInstance(this).clientDao().deleteById(client.id)
                    val clients = ClientDatabase.getInstance(this).clientDao().getAll()
                    runOnUiThread {
                        adapter.submit(clients)
                        emptyView.visibility = if (clients.isEmpty()) android.view.View.VISIBLE else android.view.View.GONE
                    }
                }
            },
            onSaveContact = { client ->
                requestContactPermissionAndSave(client)
            }
        )

        clearAllView.setOnClickListener {
            executor.execute {
                ClientDatabase.getInstance(this).clientDao().clear()
                runOnUiThread {
                    adapter.submit(emptyList())
                    emptyView.visibility = android.view.View.VISIBLE
                }
            }
        }

        listView.layoutManager = LinearLayoutManager(this)
        listView.adapter = adapter

        executor.execute {
            val clients = ClientDatabase.getInstance(this).clientDao().getAll()
            runOnUiThread {
                adapter.submit(clients)
                emptyView.visibility = if (clients.isEmpty()) android.view.View.VISIBLE else android.view.View.GONE
            }
        }
    }

    override fun onResume() {
        super.onResume()
        NotificationState.clearUnread(this)
        NotificationManagerCompat.from(this).cancel(PushMessagingService.BADGE_NOTIFICATION_ID)
    }

    private fun requestContactPermissionAndSave(client: ClientEntity) {
        if (client.name.isBlank() && client.phone.isBlank() && client.email.isBlank()) {
            Toast.makeText(this, "This customer has no contact info to save.", Toast.LENGTH_SHORT).show()
            return
        }
        val granted = ContextCompat.checkSelfPermission(
            this,
            Manifest.permission.WRITE_CONTACTS
        ) == PackageManager.PERMISSION_GRANTED

        if (granted) {
            saveClientToPhoneContacts(client)
            return
        }

        pendingContactClient = client
        contactPermissionLauncher.launch(Manifest.permission.WRITE_CONTACTS)
    }

    private fun saveClientToPhoneContacts(client: ClientEntity) {
        executor.execute {
            val success = insertContact(client)
            runOnUiThread {
                val message = if (success) {
                    "${client.name.ifBlank { "Client" }} saved to contacts."
                } else {
                    "Failed to save contact."
                }
                Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
            }
        }
    }

    private fun insertContact(client: ClientEntity): Boolean {
        return try {
            val ops = ArrayList<ContentProviderOperation>()
            ops.add(
                ContentProviderOperation.newInsert(ContactsContract.RawContacts.CONTENT_URI)
                    .withValue(ContactsContract.RawContacts.ACCOUNT_TYPE, null)
                    .withValue(ContactsContract.RawContacts.ACCOUNT_NAME, null)
                    .build()
            )

            val displayName = client.name.ifBlank {
                client.email.ifBlank { client.phone.ifBlank { "Client" } }
            }

            ops.add(
                ContentProviderOperation.newInsert(ContactsContract.Data.CONTENT_URI)
                    .withValueBackReference(ContactsContract.Data.RAW_CONTACT_ID, 0)
                    .withValue(
                        ContactsContract.Data.MIMETYPE,
                        ContactsContract.CommonDataKinds.StructuredName.CONTENT_ITEM_TYPE
                    )
                    .withValue(ContactsContract.CommonDataKinds.StructuredName.DISPLAY_NAME, displayName)
                    .build()
            )

            if (client.phone.isNotBlank()) {
                ops.add(
                    ContentProviderOperation.newInsert(ContactsContract.Data.CONTENT_URI)
                        .withValueBackReference(ContactsContract.Data.RAW_CONTACT_ID, 0)
                        .withValue(
                            ContactsContract.Data.MIMETYPE,
                            ContactsContract.CommonDataKinds.Phone.CONTENT_ITEM_TYPE
                        )
                        .withValue(ContactsContract.CommonDataKinds.Phone.NUMBER, client.phone)
                        .withValue(
                            ContactsContract.CommonDataKinds.Phone.TYPE,
                            ContactsContract.CommonDataKinds.Phone.TYPE_MOBILE
                        )
                        .build()
                )
            }

            if (client.email.isNotBlank()) {
                ops.add(
                    ContentProviderOperation.newInsert(ContactsContract.Data.CONTENT_URI)
                        .withValueBackReference(ContactsContract.Data.RAW_CONTACT_ID, 0)
                        .withValue(
                            ContactsContract.Data.MIMETYPE,
                            ContactsContract.CommonDataKinds.Email.CONTENT_ITEM_TYPE
                        )
                        .withValue(ContactsContract.CommonDataKinds.Email.ADDRESS, client.email)
                        .withValue(
                            ContactsContract.CommonDataKinds.Email.TYPE,
                            ContactsContract.CommonDataKinds.Email.TYPE_WORK
                        )
                        .build()
                )
            }

            if (client.city.isNotBlank()) {
                ops.add(
                    ContentProviderOperation.newInsert(ContactsContract.Data.CONTENT_URI)
                        .withValueBackReference(ContactsContract.Data.RAW_CONTACT_ID, 0)
                        .withValue(
                            ContactsContract.Data.MIMETYPE,
                            ContactsContract.CommonDataKinds.Note.CONTENT_ITEM_TYPE
                        )
                        .withValue(ContactsContract.CommonDataKinds.Note.NOTE, "City: ${client.city}")
                        .build()
                )
            }

            contentResolver.applyBatch(ContactsContract.AUTHORITY, ops)
            true
        } catch (_: Exception) {
            false
        }
    }
}
