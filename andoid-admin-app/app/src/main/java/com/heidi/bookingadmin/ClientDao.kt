package com.heidi.bookingadmin

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query

@Dao
interface ClientDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    fun upsert(client: ClientEntity)

    @Query("SELECT * FROM clients ORDER BY createdAt DESC")
    fun getAll(): List<ClientEntity>

    @Query("SELECT * FROM clients ORDER BY createdAt DESC LIMIT :limit")
    fun getRecent(limit: Int): List<ClientEntity>

    @Query("DELETE FROM clients WHERE id = :id")
    fun deleteById(id: String)

    @Query("DELETE FROM clients")
    fun clear()
}
