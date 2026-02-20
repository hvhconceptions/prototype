package com.heidi.bookingadmin

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase

@Database(entities = [ClientEntity::class], version = 2)
abstract class ClientDatabase : RoomDatabase() {
    abstract fun clientDao(): ClientDao

    companion object {
        @Volatile
        private var INSTANCE: ClientDatabase? = null

        fun getInstance(context: Context): ClientDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    ClientDatabase::class.java,
                    "clients.db"
                )
                    .fallbackToDestructiveMigration()
                    .build()
                INSTANCE = instance
                instance
            }
        }
    }
}
