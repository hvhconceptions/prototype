package com.heidi.bookingadmin

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "clients")
data class ClientEntity(
    @PrimaryKey val id: String,
    val name: String,
    val city: String,
    val email: String,
    val phone: String,
    val contactOk: Boolean,
    val createdAt: Long
)
