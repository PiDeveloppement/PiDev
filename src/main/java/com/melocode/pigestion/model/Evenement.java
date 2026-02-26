package com.melocode.pigestion.model;

public class Evenement {
    private int id;
    private String nom;

    public Evenement(int id, String nom) {
        this.id = id;
        this.nom = nom;
    }

    // AJOUTEZ BIEN CETTE MÉTHODE :
    public int getIdEvent() {
        return id;
    }

    public int getId() {
        return id;
    }

    public String getNom() {
        return nom;
    }

    @Override
    public String toString() {
        return nom;
    }
}