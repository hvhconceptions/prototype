plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("com.google.devtools.ksp")
    id("com.google.gms.google-services")
}

android {
    namespace = "com.heidi.bookingadmin"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.heidi.bookingadmin"
        minSdk = 24
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }

    buildFeatures {
        viewBinding = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }
}

val exportDebugApk by tasks.registering {
    dependsOn("assembleDebug")
    doNotTrackState("Copies debug APK to a fixed root filename.")
    doLast {
        val debugOutputDir = layout.buildDirectory.dir("outputs/apk/debug").get().asFile
        val sourceApk = debugOutputDir
            .listFiles()
            ?.firstOrNull { it.isFile && it.extension.equals("apk", ignoreCase = true) }
            ?: error("No debug APK found in ${debugOutputDir.absolutePath}")
        val targetApk = rootProject.layout.projectDirectory.file("app-debug.apk").asFile
        sourceApk.copyTo(targetApk, overwrite = true)
    }
}

tasks.matching { it.name == "assembleDebug" }.configureEach {
    finalizedBy(exportDebugApk)
}

dependencies {
    implementation(platform("com.google.firebase:firebase-bom:34.8.0"))
    implementation("com.google.firebase:firebase-analytics")
    implementation("com.google.firebase:firebase-messaging")

    implementation("androidx.room:room-runtime:2.6.1")
    implementation("androidx.room:room-ktx:2.6.1")
    ksp("androidx.room:room-compiler:2.6.1")

    implementation("androidx.recyclerview:recyclerview:1.3.2")
    implementation("com.google.firebase:firebase-auth")
    implementation("com.google.android.gms:play-services-auth:21.5.0")

    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.swiperefreshlayout:swiperefreshlayout:1.1.0")
    implementation("com.google.android.material:material:1.12.0")
}


