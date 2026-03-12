package com.heidi.bookingadmin

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.text.format.DateUtils
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView

class ClientAdapter(
    private val onDelete: (ClientEntity) -> Unit,
    private val onSaveContact: (ClientEntity) -> Unit
) :
    RecyclerView.Adapter<ClientAdapter.ClientViewHolder>() {
    private val items = mutableListOf<ClientEntity>()

    fun submit(list: List<ClientEntity>) {
        items.clear()
        items.addAll(list)
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ClientViewHolder {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_client, parent, false)
        return ClientViewHolder(view)
    }

    override fun onBindViewHolder(holder: ClientViewHolder, position: Int) {
        holder.bind(items[position], onDelete, onSaveContact)
    }

    override fun getItemCount(): Int = items.size

    class ClientViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val nameView: TextView = itemView.findViewById(R.id.clientName)
        private val metaView: TextView = itemView.findViewById(R.id.clientMeta)
        private val contactView: TextView = itemView.findViewById(R.id.clientContact)
        private val saveContactView: TextView = itemView.findViewById(R.id.clientSaveContact)
        private val deleteView: TextView = itemView.findViewById(R.id.clientDelete)

        fun bind(
            item: ClientEntity,
            onDelete: (ClientEntity) -> Unit,
            onSaveContact: (ClientEntity) -> Unit
        ) {
            nameView.text = item.name.ifBlank { "Unknown" }
            val where = item.city.ifBlank { "City not set" }
            val who = item.email.ifBlank { "No email" }
            metaView.text = "City: $where\nEmail: $who"
            val whenText = DateUtils.getRelativeTimeSpanString(
                item.createdAt,
                System.currentTimeMillis(),
                DateUtils.MINUTE_IN_MILLIS
            ).toString()
            val contactLabel = if (item.contactOk) "Follow-up: YES" else "Follow-up: NO"
            val phoneText = item.phone.ifBlank { "No phone" }
            contactView.text = "Phone: $phoneText\n$contactLabel\nUpdated: $whenText"
            saveContactView.text = "Save contact"
            saveContactView.setOnClickListener {
                onSaveContact(item)
            }
            deleteView.text = "Remove"
            deleteView.setOnClickListener {
                onDelete(item)
            }
        }
    }
}
