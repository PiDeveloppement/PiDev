// file: src/main/java/com/example/pidev/model/Auth/AuthContext.java
package com.example.pidev.model.Auth;

public final class AuthContext {

    private static String currentEmail;
    private static Role currentRole;

    private AuthContext() {}

    public static void set(String email, Role role) {
        currentEmail = (email == null) ? null : email.trim();
        currentRole  = role;
    }

    public static void clear() {
        currentEmail = null;
        currentRole = null;
    }

    // ✅ tes contrôleurs utilisent getCurrentEmail()
    public static String getCurrentEmail() {
        return currentEmail;
    }

    // ✅ pour compatibilité si tu as du code qui appelle getEmail()
    public static String getEmail() {
        return currentEmail;
    }

    public static Role getCurrentRole() {
        return currentRole;
    }
    public static void setDevAdmin(String email) {
        set(email, Role.ADMIN);
    }


    public static boolean isAdmin() {
        return currentRole == Role.ADMIN;
    }
}
