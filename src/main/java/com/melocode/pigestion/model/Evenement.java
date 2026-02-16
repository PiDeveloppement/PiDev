package com.melocode.pigestion.model;

public class Evenement {
    private int id;
    private String nom;

    public Evenement(int id, String nom) {
        this.id = id;
        this.nom = nom;
    }

    public int getId() { return id; }
    public String getNom() { return nom; }

    @Override
    public String toString() {
        return nom; // Affiche le nom dans la ComboBox
    }
}