plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "com.heidi.hvhwebapp"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.heidi.hvhwebapp"
        minSdk = 24
        targetSdk = 34
        versionCode = 6
        versionName = "1.6"
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
    doNotTrackState("Copies website app APK to deploy download path.")
    doLast {
        val debugOutputDir = layout.buildDirectory.dir("outputs/apk/debug").get().asFile
        val sourceApk = debugOutputDir
            .listFiles()
            ?.firstOrNull { it.isFile && it.extension.equals("apk", ignoreCase = true) }
            ?: error("No debug APK found in ${debugOutputDir.absolutePath}")
        val repoRoot = rootProject.layout.projectDirectory.asFile.parentFile
        val targetApk = File(repoRoot, "downloads/HVH-Website.apk")
        targetApk.parentFile?.mkdirs()
        sourceApk.copyTo(targetApk, overwrite = true)
    }
}

tasks.matching { it.name == "assembleDebug" }.configureEach {
    finalizedBy(exportDebugApk)
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("com.google.android.material:material:1.12.0")
}
