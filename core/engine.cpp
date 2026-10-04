#include <iostream>
#include <string>

using namespace std;

int main(int argc, char* argv[]) {
    if (argc < 7) { cout << "33,34,33"; return 0; }

    // Parámetros: GF_L, GC_L, GF_V, GC_V, Corners_L, Corners_V
    float gfL = atof(argv[1]);
    float gcL = atof(argv[2]);
    float gfV = atof(argv[3]);
    float gcV = atof(argv[4]);
    float coL = atof(argv[5]); // Corners Local
    float coV = atof(argv[6]); // Corners Visitante

    // Algoritmo Real: Los corners aumentan la presión ofensiva
    float ataqueL = (gfL * 0.7) + (coL * 0.3) + gcV;
    float ataqueV = (gfV * 0.7) + (coV * 0.3) + gcL;

    float total = ataqueL + ataqueV + 0.1;
    float pL = (ataqueL / total) * 100;
    float pV = (ataqueV / total) * 100;
    float pE = 100 - (pL + pV);

    cout << pL << "," << pE << "," << pV;
    return 0;
}