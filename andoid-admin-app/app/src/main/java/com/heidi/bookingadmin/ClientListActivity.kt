package com.heidi.bookingadmin

import android.os.Bundle
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import java.util.concurrent.Executors

class ClientListActivity : AppCompatActivity() {
    private val executor = Executors.newSingleThreadExecutor()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_clients)

        val listView = findViewById<RecyclerView>(R.id.clientsList)
        val emptyView = findViewById<TextView>(R.id.emptyClients)
        lateinit var adapter: ClientAdapter
        adapter = ClientAdapter { client ->
            executor.execute {
                ClientDatabase.getInstance(this).clientDao().deleteByEmail(client.email)
                val clients = ClientDatabase.getInstance(this).clientDao().getAll()
                runOnUiThread {
                    adapter.submit(clients)
                    emptyView.visibility = if (clients.isEmpty()) android.view.View.VISIBLE else android.view.View.GONE
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
}
