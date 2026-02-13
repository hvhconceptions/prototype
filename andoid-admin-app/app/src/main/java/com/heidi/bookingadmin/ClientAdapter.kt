package com.heidi.bookingadmin

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView

class ClientAdapter(private val onDelete: (ClientEntity) -> Unit) :
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
        holder.bind(items[position], onDelete)
    }

    override fun getItemCount(): Int = items.size

    class ClientViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val nameView: TextView = itemView.findViewById(R.id.clientName)
        private val metaView: TextView = itemView.findViewById(R.id.clientMeta)
        private val contactView: TextView = itemView.findViewById(R.id.clientContact)
        private val deleteView: TextView = itemView.findViewById(R.id.clientDelete)

        fun bind(item: ClientEntity, onDelete: (ClientEntity) -> Unit) {
            nameView.text = item.name.ifBlank { "Unknown" }
            metaView.text = "${item.city} - ${item.email}"
            val contactLabel = if (item.contactOk) "Contact: YES" else "Contact: NO"
            contactView.text = "${item.phone} - $contactLabel"
            deleteView.setOnClickListener {
                onDelete(item)
            }
        }
    }
}
